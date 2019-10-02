<?php
require_once('vendor/autoload.php'); // https://getcomposer.org/download/
use PHPUnit\Framework\TestCase;
include_once 'ds_config_files.php';

$timeCheckNumber = 0;
$timeChecks  = array();
$modeName = "Select at the bottom of the file";
$successes = 0;
$enqueueErrors = 0;
$dequeueErrors = 0;
$testsSent = array();
$foundAll = false;
//$testMessagesDir = realpath(dirname(__FILE__) . "/../test_messages");
$testMessagesDir =  dirname(__FILE__) . DIRECTORY_SEPARATOR . "test_messages";

class RunTest extends \PHPUnit\Framework\TestCase{

    public static function test_run($mode){
        global $timeChecks, $modeName;
        $modeName = $mode;
        for($index=0; $index<8; $index+=1){
            // ask larry why showing one hour before 
            $timeChecks[] = date("Y-m-d H:i:s", strtotime(sprintf("+%d hours", $index+1)));
        }
        print(date('Y/m/d H:i:s') . " Starting\n");
        RunTest::doTests();
        print(date('Y/m/d H:i:s') . " Done\n");
    }
    private static function doTests(){
        global $timeCheckNumber, $timeChecks, $modeName;
        while($timeCheckNumber <= 7){
            $now = date("Y-m-d H:i:s");
            while($timeChecks[$timeCheckNumber]>$now){
                RunTest::doTest();
                if($modeName === "few"){
                    $now = date('Y/m/d H:i:s');
                    $seconds_to_sleep = abs(strtotime($timeChecks[$timeCheckNumber]) - strtotime($now))+2;
                    sleep($seconds_to_sleep);
                }
            }
            RunTest::showStatus();
            $timeCheckNumber += 1;
        }
        RunTest::showStatus();
    }

    private static function showStatus(){
        global $successes, $enqueueErrors, $dequeueErrors;
        $rate = (100.0* $successes)/($enqueueErrors + $dequeueErrors + $successes);
        print(date('Y/m/d H:i:s') ."#### Test statistics: $successes (".round($rate,2)."%) successes, $enqueueErrors enqueue errors, $dequeueErrors dequeue errors.\n");
    }

    private static function doTest(){
        global $foundAll, $testsSent, $successes, $dequeueErrors;
        RunTest::send();
        $endTime = date("Y/m/d H:i:s", strtotime(sprintf("+%d minutes", 3)));
        $foundAll = false;
        $tests = sizeof($testsSent);
        $successesStart = $successes;     
        while(!$foundAll && $endTime>date("Y/m/d H:i:s")){
            sleep(1);
            RunTest::checkResults();
        }
        
        if(!$foundAll){
            $dequeueErrors += sizeof($testsSent);
        }
        print("Test: $tests sent, " . ($successes-$successesStart) ." successes, ".sizeof($testsSent)." failures.\n");
    }

    private static function checkResults(){
        global $testMessagesDir, $testsSent, $successes, $foundAll;
        $testsReceived = array();
        $file_data = "";
        for($index = 0; $index<20 ; $index+=1){
            $file_data = "";
            try{
                // The path of the files created of Test mode
                $test_file = $testMessagesDir . DIRECTORY_SEPARATOR . "test" . $index .".txt";
                if(file_exists($test_file)){
                    $handle = fopen($test_file, "r");
                    $file_data = fgets($handle);
                    //$file_data = fread($handle, filesize($test_file));
                    if(!$file_data==null && !$file_data==""){
                        $testsReceived[]=$file_data;
                    }   
                    fclose($handle);
                }
            }
            catch(IOException $e){
                print("Could not open the file: $e");
            }
        }
        // Create a private copy of testsSent (testsSentOrig) and reset testsSent
        // Then, for each element in testsSentOrig not found, add back to testsSent.
        $testsSentOrig = $testsSent;
        $testsSent= array();
        foreach($testsSentOrig as $testValue){
            if(in_array($testValue, $testsReceived)){
                $successes += 1;
            }
            else{
                $testsSent[] = $testValue;
            }
        }
        // Update foundAll
        $foundAll = (sizeof($testsSent) == 0);
    }

    private static function send(){
        global $testsSent, $enqueueErrors;
        $testsSent= array();
        for($index=0 ; $index<5 ; $index+=1){
            try{
                $now = round(microtime(true) * 1000);
                $testValue = "" . $now;
                RunTest::send1($testValue);
                $testsSent[] = $testValue;
            }
            catch(Exception $e){
                $enqueueErrors += 1;
                print("send: Enqueue error: $e");
                sleep(30);
            }
        }
    }

    private static function send1($test){
        try{
            sleep(0.5);
            $url = ds_config("ENQUEUE_URL") . "?test=" . $test;
            // Create a cURL handle
            // By default, cURL uses GET requests
            $curl = curl_init();
            $auth = RunTest::authObject();
            if($auth){
                $header = array();
                $header[] = 'Authorization: Basic '.base64_encode($auth);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            }
            curl_setopt($curl,CURLOPT_URL, $url);
            curl_setopt($curl,CURLOPT_TIMEOUT, 20);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
            // Execute
            curl_exec($curl);
            // Check HTTP status code
            if (!curl_errno($curl)) {
                switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                    case 200:  # OK
                        break;
                    default:
                        print('send1: GET not worked, StatusCode= '. $http_code. "\n");
                }
            }
            // Close handle
            curl_close($curl);
        }
        catch(Exception $e){
            print("send: Enqueue error: $e");
            sleep(30);
        }
    }

    private static function authObject(){
        if(!(ds_config("BASIC_AUTH_NAME")==null) && !(ds_config("BASIC_AUTH_NAME")=="{BASIC_AUTH_NAME}") && !(ds_config("BASIC_AUTH_PW")==null) && !(ds_config("BASIC_AUTH_PW")=="{BASIC_AUTH_PS}")){
            return (ds_config("BASIC_AUTH_NAME") . ":" . ds_config("BASIC_AUTH_PW"));
        }
        else{
            return false;
        }
    }
}

RunTest::test_run("meny");
