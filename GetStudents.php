<?php

namespace wonde\PhpClient;

require 'vendor/autoload.php';

class WondeClient
{
    private $accessToken;
    private $schoolId;

    /**
    * Constructor for the WondeClient class.
    *
    * @param string $accessToken The access token for the Wonde API.
    * @param string $schoolId    The ID of the school.
    */
    public function __construct($accessToken, $schoolId)
    {
        // Assign the provided values to class properties
        $this->accessToken = $accessToken;
        $this->schoolId = $schoolId;
    }

    /**
     * Retrieves the students by day for the given employee ID.
     *
     * @param string $employeeId The ID of the employee (teacher).
     * @return array The students grouped by day, class, and period.
     * @throws \Exception If there are errors retrieving or processing the data.
     */
    public function getStudentsByDay($employeeId)
    {
        $teacherUrl = "https://api.wonde.com/v1.0/schools/{$this->schoolId}/employees/{$employeeId}?include=classes.lessons.period";
        $teacherData = $this->sendRequest($teacherUrl);

        if ($teacherData === null || !isset($teacherData['data']['classes'])) {
            throw new \Exception('Failed to retrieve teacher data.');
        }

        $classes = $teacherData['data']['classes']['data'];

        // Catch if teacher has no classes
        if (empty($classes)) {
            throw new \Exception('This teacher has no classes.');
        }

        $studentsByDay = [];

        foreach ($teacherData['data']['classes']['data'] as $class) {
            if (!isset($class['id']) || !isset($class['name']) || !isset($class['lessons']['data'])) {
                // Skip invalid class data
                echo 'Invalid class data. Skipping... Class Data: ' . PHP_EOL;
                continue;
            }

            $classId = $class['id'];
            $className = $class['name'];

            $classUrl = "https://api.wonde.com/v1.0/schools/{$this->schoolId}/classes/{$classId}?include=students";
            $classData = $this->sendRequest($classUrl);

            if ($classData === null || !isset($classData['data']['students'])) {
                // Skip if failed to retrieve class data
                echo "Failed to retrieve class data for class '{$className}'. Skipping..." . PHP_EOL;
                continue;
            }

            $students = $classData['data']['students']['data'];

            foreach ($class['lessons']['data'] as $lesson) {
                if (!isset($lesson['period']['data']['day'])) {
                    // Skip invalid lesson data
                    echo 'Invalid lesson data. Skipping...' . PHP_EOL;
                    continue;
                }

                $dayOfWeek = $lesson['period']['data']['day'];
                $period = $lesson['period']['data']['name'];

                if (!isset($studentsByDay[$dayOfWeek][$className][$period])) {
                    // Initialize the student array for the day, class, and period
                    $studentsByDay[$dayOfWeek][$className][$period] = [];
                }

                foreach ($students as $student) {
                    $forename = $student['forename'];
                    $surname = $student['surname'];
                    $studentsByDay[$dayOfWeek][$className][$period][] = $forename . ' ' . $surname;
                }
            }
        }

        foreach ($studentsByDay as $dayOfWeek => $classes) {
            echo PHP_EOL;
            // Display day of the week as a header
            echo "┌────────────────────────────────────────────────────────────────────────────────┐" . PHP_EOL;
            echo "│" . str_pad("Day of the Week", 80, " ", STR_PAD_BOTH) . "│" . PHP_EOL;
            echo "│" . str_pad($dayOfWeek, 80, " ", STR_PAD_BOTH) . "│" . PHP_EOL;
            echo "└────────────────────────────────────────────────────────────────────────────────┘" . PHP_EOL . PHP_EOL;

            foreach ($classes as $className => $periods) {
                echo "\033[1m-- Class Name: $className --\033[0m" . PHP_EOL;

                foreach ($periods as $period => $students) {
                    echo "\033[1m-- Period: $period --\033[0m" . PHP_EOL . PHP_EOL;

                    foreach ($students as $student) {
                        echo "Student: $student" . PHP_EOL;
                    }

                    echo PHP_EOL;
                }
            }

            echo PHP_EOL;
        }

        return $studentsByDay;
    }

    /**
     * Sends an HTTP request to the specified URL using cURL.
     *
     * @param string $url The URL to send the request to.
     * @return array The response data from the API.
     * @throws \Exception If there are errors with the cURL request or API response.
     */
    private function sendRequest($url)
    {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Accept: application/json',
        ]);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        if ($response === false) {
            // Handle the cURL request error
            $errorMessage = curl_error($curl);
            curl_close($curl);
            throw new \Exception('cURL request error: ' . $errorMessage);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            // Handle the API response error
            $responseData = json_decode($response, true);
            $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
            curl_close($curl);
            throw new \Exception('API response error: ' . $errorMessage);
        }

        curl_close($curl);

        return json_decode($response, true);
    }
}

$accessToken = 'b05f975d836592a32aa48a222575f1082f4de830';
$schoolId = 'A1930499544';

$wondeClient = new WondeClient($accessToken, $schoolId);

// Prompt the user to enter their employee ID
$employeeId = readline('Enter your employee ID: ');

try {
    $wondeClient->getStudentsByDay($employeeId);
} catch (\Exception $e) {
    echo 'An error occurred: ' . $e->getMessage() . PHP_EOL;
}
