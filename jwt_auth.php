<?php

    use \DocuSign\eSign\Configuration;
    define('TOKEN_REPLACEMENT_IN_SECONDS', 600); //10 minutes
    define('TOKEN_EXPIRATION_IN_SECONDS', 3600); //1 hour
    
    $exp = 3600;

    $permission_scopes="signature%20impersonation";
    $redirect_uri ="https://www.docusign.com";

    $access_token;
    $expiresInTimestamp;
    $accountID;
    $base_uri;
    $config = Configuration::getDefaultConfiguration();
    $apiClient = new DocuSign\eSign\client\ApiClient($config);

    function checkToken() {
        global $access_token, $expiresInTimestamp;
        if(is_null($access_token)
                || (time() + TOKEN_REPLACEMENT_IN_SECONDS) > $expiresInTimestamp) {
            updateToken();
        }
    }

    function updateToken(){
        print("Requesting an access token via the JWT flow...\n");
        global $account, $apiClient, $access_token, $accountID, $base_uri, $expiresInTimestamp;
        $apiClient->getOAuth()->setOAuthBasePath(ds_config("DS_AUTH_SERVER"));
        $token = $apiClient->requestJWTUserToken($client_id = ds_config("DS_CLIENT_ID"), $user_id = ds_config("DS_IMPERSONATED_USER_GUID"),
                                                $rsa_private_key = ds_config("DS_PRIVATE_KEY"), $scopes = "signature");
        $access_token = $token[0]["access_token"];
        if(is_null($account)) {
            $account = get_account_info($apiClient);
        }
        $accountID = $account->getAccountId();
        $base_uri = $account->getBaseUri() . "/restapi";
        $expiresInTimestamp = time() + TOKEN_EXPIRATION_IN_SECONDS;
        print ("Done. Continuing...\n");
        $config = $apiClient->getConfig();
        $config->setAccessToken($access_token);
        $config->setHost($base_uri);
        $config->addDefaultHeader("Authorization", "Bearer ". $access_token);
        $apiClient = new DocuSign\eSign\client\ApiClient($config);
    } 

    function get_account_info($client){
        global $apiClient, $access_token;
        // to set the authontication server we set the base path to temp value
        // for demo - set to https://demo.docusign.net for production set to https://www.docusign.net
        // see the https://github.com/docusign/docusign-php-client/blob/master/src/Client/Auth/OAuth.php#L60
        $userInfo = $apiClient->getUserInfo($access_token);
        $accountInfo = $userInfo[0]["accounts"];
        if(ds_config("DS_TARGET_ACCOUNT_ID") === "False"){
            foreach($accountInfo as $acct){
                // Look for default
                if($acct->getIsDefault()){
                    return $acct;
                }
            }
        }

        foreach($accountInfo as $acct){
            if($acct->getAccountId() === ds_config("DS_TARGET_ACCOUNT_ID")){
                return $acct;
            }
        }
        $date = date('Y/m/d H:i:s');
        throw new Exception("$date User does not have access to account\n");

    }

    

