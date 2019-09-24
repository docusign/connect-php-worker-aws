<?php

    include_once 'ds_config_files.php';
    include_once 'aws_worker.php';
    $current_directory = dirname(__FILE__);
    
    function process($test, $xml){
        // Send the message to the test mode
        if($test !== ''){
            processTest($test);
        }

        // In test mode there is no xml sting, should be checked before trying to parse it
        if($xml !== ''){
            // Step 1. parse the xml
            $root = simplexml_load_string($xml);

            // Extract from the XML the fields values
            $envelopeElement = $root->{'EnvelopeStatus'};
            $envelopeId = $envelopeElement->{'EnvelopeID'};
            $subject = $envelopeElement->{'Subject'};
            $senderName =  $envelopeElement->{'UserName'};
            $senderEmail = $envelopeElement->{'Email'};
            $status =  $envelopeElement->{'Status'};
            $created =  $envelopeElement->{'Created'};
            $orderNumber = $envelopeElement->{'CustomFields'}->{'CustomField'}->{'Value'};

            if($status == 'Completed'){
                $completedMsg = 'Completed: true';
            }
            else{
                $completedMsg = '';
            }

            // For debugging, you can print the entire notification
            print("EnvelopeId: $envelopeId\n");
            printf("Subject: $subject\n");
            print("Sender: $senderName, $senderEmail\n");
            print("Order Number: $orderNumber\n");
            print("Status: $status\n");
            print("Sent: $created, $completedMsg\n");

            // Step 2. Filter the notifications
            $ignore = false;
            // Check if the envelope was sent from the test mode 
            // If sent from test mode - ok to continue even if the status not equals to Completed
            if($orderNumber != 'Test_Mode'){
                if($status != 'Completed'){
                    $ignore = true;
                    if(ds_config('DEBUG') === 'true'){
                        print(date('Y/m/d H:i:s')." IGNORED: envelope status is $status\n");
                    }
                }
            }
            if($orderNumber === NULL || $orderNumber == ""){
                $ignore = true;
                if(ds_config('DEBUG') === 'true'){
                    $custom_field = ds_config("ENVELOPE_CUSTOM_FIELD");
                    print(date('Y/m/d H:i:s')." IGNORED: envelope does not have a $custom_field envelope custom field.\n");
                }
            }

            // Step 3. (Future) Check that this is not a duplicate notification
            // The queuing system delivers on an "at least once" basis. So there is a
            // chance that we have already processes this notification.
            //
            // For this example, we'll just repeat the document fetch if it is duplicate notification

            // Step 4 save the document - it can raise an exception which will be caught at startQueue 
            if(!$ignore){
                saveDoc($envelopeId, $orderNumber);
            }
        }
    }

    function saveDoc($envelopeId, $orderNumber){
        try{
            // All variables were initialized in main.php when checkToken() function were called 
            global $config, $apiClient, $access_token, $accountID, $base_uri;
            $config->setHost($base_uri);
            $config->addDefaultHeader("Authorization", "Bearer ". $access_token);
            $envelope_api = new DocuSign\eSign\Api\EnvelopesApi($apiClient);
            $temp_file = $envelope_api->getDocument($accountID, "combined", $envelopeId);
            
            // Create the test directory if needed
            global $current_directory;
            $output_directory = $current_directory . DIRECTORY_SEPARATOR . "output";
            if (!file_exists($output_directory)) {
                mkdir($output_directory, 0777, true); // Note that 0777 is already the default mode for directories and may still be modified by the current umask.
                if (!file_exists($output_directory)) {
                    throw new Exception(date('Y/m/d H:i:s')." Failed to create directory");
                }
            }
            $filePath = $output_directory . DIRECTORY_SEPARATOR . ds_config("OUTPUT_FILE_PREFIX") . $orderNumber . ".pdf";
            rename($temp_file->getPathname() , $filePath);
            if(!file_exists($filePath)){
                throw new Exception(date('Y/m/d H:i:s')." Failed to create file");
            }
        }
        catch (ApiException $e) {
            print(date('Y/m/d H:i:s')." API exception: $e. saveDoc error");
        }
        catch(Exception $e){
            print(date('Y/m/d H:i:s')." Error while fetching and saving docs for envelope $envelopeId, order $orderNumber");
            print(date('Y/m/d H:i:s')." saveDoc error $e");
        }
        
    }

    function processTest($test){
        if(ds_config("ENABLE_BREAK_TEST") === "true" && strpos($test, 'empty')){
            print(date('Y/m/d H:i:s')." BREAKING worker test!");
            exit(2);
        }

        print(date('Y/m/d H:i:s')." Processing test value $test\n");

        // Create the test directory if needed
        global $current_directory;
        $test_directory = $current_directory . DIRECTORY_SEPARATOR . "test_messages";
        if (!file_exists($test_directory)) {
            mkdir($test_directory, 0777, true); // Note that 0777 is already the default mode for directories and may still be modified by the current umask.
            if (!file_exists($test_directory)) {
                print(date('Y/m/d H:i:s')." Failed to create directory");
            }
        }

        // First shuffle test1 to test2 (if it exists) and so on
        for($index=9; $index>0; $index-=1){
            $old_file = $test_directory . DIRECTORY_SEPARATOR . "test" . $index . ".txt";
            $new_file = $test_directory . DIRECTORY_SEPARATOR . "test" . ($index+1) . ".txt";
            if(file_exists($old_file)){
                if(file_exists($new_file)){
                    unlink($new_file); // If the new_file already exists, delete it
                }
                rename($old_file , $new_file);
            }
        }

        // The new test message will be placed in test1 - creating new file
        $file_path = fopen($test_directory . DIRECTORY_SEPARATOR . "test1.txt","wb");
        fwrite($file_path,$test);
        fclose($file_path);
    }