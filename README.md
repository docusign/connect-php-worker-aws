# PHP: Connect Worker for AWS

Repository: [connect-php-worker-aws](https://github.com/docusign/connect-php-worker-aws)

## Introduction

This is an example worker application for
Connect webhook notification messages sent
via the [AWS SQS (Simple Queueing System)](https://aws.amazon.com/sqs/).

This application receives DocuSign Connect
messages from the queue and then processes them:

* If the envelope is complete, the application
  uses a DocuSign JWT Grant token to retrieve
  the envelope's combined set of documents,
  and stores them in the `output` directory.
  
   For this example, the envelope **must** 
   include an Envelope Custom Field
   named `Sales order.` The Sales order field is used
   to name the output file.

## Architecture

![Connect listener architecture](demo_documents/connect_listener_architecture.png)

AWS has [SQS](https://aws.amazon.com/tools/)
SDK libraries for C#, Java, Node.js, Python, Ruby, C++, and Go. 

## Installation

### Introduction
First, install the **Lambda listener** on AWS and set up the SQS queue.

Then set up this code example to receive and process the messages
received via the SQS queue.

### Installing the Lambda Listener

Install the example 
   [Connect listener for AWS](https://github.com/docusign/connect-node-listener-aws)
   on AWS.
   At the end of this step, you will have the
   `Queue URL`, `Queue Region` and `Enqueue url` that you need for the next step.

### Installing the worker (this repository)

#### Requirements
* PHP version 7.3.5 or later

1. Download or clone this repository. Then:

   ````
   cd connect-php-worker-aws
   composer install
   ````
1. Using AWS IAM, create an IAM `User` with access to your SQS queue.

1. Configure the **ds_config.ini** file: [ds_config.ini](ds_config.ini)
    The application uses the OAuth JWT Grant flow.

    If consent has not been granted to the application by
    the user, then the application provides a url
    that can be used to grant individual consent.

    **To enable individual consent:** either
    add the URL [https://www.docusign.com](https://www.docusign.com) as a redirect URI
    for the Integration Key, or add a different URL and
    update the `oAuthConsentRedirectURI` setting
    in the ds_config.ini file.

5.  Creating the Integration Key
    Your DocuSign Integration Key must be configured for a JWT OAuth authentication flow:
    * Create a public/private key pair for the key. Store the private key
    in a secure location. You can use a file or a key vault.
    * The example requires the private key. Store the private key in the
    [ds_config.ini](ds_config.ini) file.
  
    **Note:** the private key's second and subsequent
    lines need to have a space added at the beginning due
    to requirements from the Python configuration file
    parser. Example:

````  
DS_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
....
XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXz1pr9mKfwmamtPXeMSJeEZUVmh7mNx
PEHgznlGh/vUboCuA4tQOcKytxFfKG4F+jM/g4GH9z46KZOow3Hb6g==
-----END RSA PRIVATE KEY-----"  
````  
  
## Run the examples

````
php aws_worker.php
````
## Testing
Configure a DocuSign Connect subscription to send notifications to
the Cloud Function. Create / complete a DocuSign envelope.
The envelope **must include an Envelope Custom Field named "Sales order".**

* Check the Connect logs for feedback.
* Check the console output of this app for log output.
* Check the `output` directory to see if the envelope's
  combined documents and CoC were downloaded.

  For this code example, the 
  envelope's documents will only be downloaded if
  the envelope is `complete` and includes a 
  `Sales order` custom field.

## Unit Tests
Includes three types of testing:
* [SavingEnvelopeTest.cs](UnitTests/SavingEnvelopeTest.cs) allow you to send an envelope to your amazon sqs from the program. The envelope is saved at `output` directory although its status is `sent`.

* [RunTest.cs](UnitTests/RunTest.cs) divides into two types of tests, both submits tests for 8 hours and updates every hour about the amount of successes or failures that occurred in that hour, the differences between the two are:
    * `few` - Submits 5 tests every hour.
    * `many` - Submits many tests every hour.

To run the tests, split the terminal in two inside Visual Studio Code. In the first terminal run the connect-csharp-worker-aws program. In the second terminal choose the wanted test. You can see above at `Run the examples` part how the files can be run.

**Note:** make sure your composer conatains:
````
    "require-dev": {
        "phpunit/phpunit": "8.3"
    }
````   
If not, write in the terminal:
```` 
composer require --dev phpunit/phpunit ^8.3
````
## Support, Contributions, License

Submit support questions to [StackOverflow](https://stackoverflow.com). Use tag `docusignapi`.

Contributions via Pull Requests are appreciated.
All contributions must use the MIT License.

This repository uses the MIT license, see the
[LICENSE](https://github.com/docusign/connect-php-worker-aws/blob/master/LICENSE) file.
