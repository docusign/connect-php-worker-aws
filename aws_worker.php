<?php
    require_once('vendor/autoload.php'); // https://getcomposer.org/download/
    require_once('vendor/docusign/esign-client/autoload.php');
    include_once 'ds_config_files.php';
    include_once 'process_notification.php';
    include_once 'jwt_auth.php';
    use Aws\Sqs\SqsClient;

    $sqs = SqsClient::factory(array(

        'credentials' => array (
        'key' => ds_config("AWS_ACCOUNT"), //use your AWS key here
        'secret' => ds_config("AWS_SECRET") //use your AWS secret here
        ),
        
        'region' => ds_config("QUEUE_REGION"), //replace it with your region
        'version' => 'latest'
        ));

    $restart = true;
    $checkLogQ = array(); //Queue

    function listenForever(){
        testToken();
        while(true){
        global $restart; 
            if($restart){
                print(date('Y/m/d H:i:s')." Starting queue worker\n"); 
                $restart = false;
                startQueue();
            }
            sleep(5);
        }
    }

    function testToken(){
        try{
            if(ds_config("DS_CLIENT_ID")==="{CLIENT_ID}"){
                throw new Exception("Problem: you need to configure this example, either via environment variables (recommended) \n" . 
                "or via the ds_configuration.js file. \nSee the README file for more information\n\n");
                exit(0);
            }
            checkToken();
        }
        catch (ApiException $e) {
            if (property_exists ($e, 'error') and $e->{'error'} == 'consent_required' ){
                $consent_url = "https://{$aud}/oauth/auth?response_type=code&scope={permission_scopes}&client_id={$iss}&redirect_uri={redirect_uri}";
                
                throw new Exception("\n\n".date('Y/m/d H:i:s')." C O N S E N T   R E Q U I R E D\n"
                ."Ask the user who will be impersonated to run the following url:\n"
                ."    {$consent_url}\n"
                ."It will ask the user to login and to approve access by your application.\n\n"
                ."Alternatively, an Administrator can use Organization Administration to\n"
                ."pre-approve one or more users.\n\n", 401);
                exit(0);
            }
        }
        catch (Exception $e){
            print(date('Y/m/d H:i:s')." $e");
        }
    }

    // Receive and wait for messages from queue
    function startQueue(){

        // Maintain the array checkLogQ as a FIFO buffer with length 4.
        // When a new entry is added, remove oldest entry and shuffle.
        function addCheckLog($message){
            global $checkLogQ;
            $length = 4;
            // If checkLogQ size is smaller than 4 add the message
            if(count($checkLogQ)<$length){
                $checkLogQ[] = $message;
            }
            // If checkLogQ size is bigger than 4 - Remove the oldest message and add the new one
            else{
                array_shift($checkLogQ);
                $checkLogQ[] = $message;
            }
        }
        // Prints all checkLogQ messages to the console  
        function printCheckLogQ(){
            global $checkLogQ;
            foreach($checkLogQ as $message){
                print($message);
            }
            $checkLogQ = array(); // reset a variable to an empty array
        }
        
        try{
            while(true){
                addCheckLog(date('Y/m/d H:i:s')." Awaiting a message...\n");

                // Receive messages from queue, maximum waits for 20 seconds for message
                // receive_request - contain all the queue messages
                global $sqs;
                $receive_request = $sqs->receiveMessage(array(
                    'QueueUrl'            => ds_config("QUEUE_URL"),
                    'WaitTimeSeconds'     => 20,
                    'MaxNumberOfMessages' => 10
                ));
                // Count the amount of messages received
                $msgCount = 0;
                if($receive_request->getPath('Messages') !== NULL){
                    $msgCount = count($receive_request->getPath('Messages'));
                }
                addCheckLog(date('Y/m/d H:i:s')." found $msgCount message(s)\n");
                // If at least one message has been received
                if ($msgCount!=0) {
                    printCheckLogQ();
                    foreach ($receive_request->getPath('Messages') as $msg) {
                        messageHandle($msg, $receive_request);
                    }
                }
            }
        }
        catch (Exception $e) {
            printCheckLogQ();
            print(date('Y/m/d H:i:s')." Queue receive error: $e");
            sleep(5);
            global $restart;
            $restart = true;
        }
    }

    function messageHandle($message, $receive_request){
        if(ds_config("DEBUG") === "true"){
            $messageId = $message['MessageId'];
            print(date('Y/m/d H:i:s'). " Processing message id: $messageId \n");
        }
        try{
            // Creates a Json object from the message body
            $body = json_decode($message['Body']);
        }
        catch(Exception $e){
            $body = false;
        }
        if($body){
            $test = $body->{'test'};
            $xml = $body->{'xml'};
            process($test, $xml);
        }
        else{
            print(date('Y/m/d H:i:s')." Null or bad body in message id $messageId. Ignoring. \n");
        }
        
        global $sqs;
        $receive_request = $sqs->deleteMessage(array(
            'QueueUrl'      => ds_config("QUEUE_URL"),
            'ReceiptHandle' => $message['ReceiptHandle']
        ));
        
    }

    listenForever();
