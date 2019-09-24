<?php
require_once('vendor/autoload.php'); // https://getcomposer.org/download/
require_once('vendor/docusign/esign-client/autoload.php');
use PHPUnit\Framework\TestCase;
include_once 'ds_config_files.php';
include_once 'create_envelope.php';
$current_directory = dirname(__FILE__);

class SaveEnvelope extends \PHPUnit\Framework\TestCase{

    public static function send_envelope(){
        try{
            print(date('Y/m/d H:i:s')." Starting\n");
            print("Sending an envelope. The envelope includes HTML, Word, and PDF documents.\nIt takes about 15 seconds for DocuSign to process the envelope request...\n");
            $result = send();
            //print("Envelope status: ". $results->getStatus() . " Envelope ID: " .$results->getEnvelopeId());
            SaveEnvelope::created();
            print(date('Y/m/d H:i:s')." Done\n");
        }

        catch (ApiException $e) {
            print("DocuSign Exception!");
        }
        catch (Exception $e){
            print("Could not open the file: $e");
        }
    }
    private static function created(){
        global $current_directory;
        sleep(30);
        if(!file_exists($current_directory . DIRECTORY_SEPARATOR . "output" . DIRECTORY_SEPARATOR . "order_Test_Mode.pdf")){
            throw new Exception("not working");
        }
    }
}

SaveEnvelope::send_envelope();