#!/usr/bin/php
<?php

    $sugarurl = 'https://mycrm.sugarondemand.com/rest/v10';
    $sugaruser = 'myuser';
    $sugarpwd = 'mypassword';
    $oauthtoken = null;
    
    $module = 'my_module';
    $batch_size = 50;
    
    //function to make Sugar cURL request
    function call($url, $type='GET', $arguments=array(), $encodeData=true, $returnHeaders=false) {
        global $sugarurl, $oauthtoken;
        $type = strtoupper($type);
        if ($type == 'GET'){
            $url .= "?" . http_build_query($arguments);
            //echo $url;
        }
        $curl_request = curl_init($sugarurl.$url);
        if ($type == 'POST'){
            curl_setopt($curl_request, CURLOPT_POST, true);
        }
        elseif ($type == 'PUT'){
            curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "PUT");
        }
        elseif ($type == 'DELETE'){
            curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl_request, CURLOPT_HEADER, $returnHeaders);
        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);

        if ($oauthtoken != null){
            curl_setopt($curl_request, CURLOPT_HTTPHEADER, array("oauth-token: {$oauthtoken}"));
        }
        else {
            //   not logged in
        }
        if (!empty($arguments) && $type !== 'GET'){
            if ($encodeData){
                //encode the arguments as JSON
                $arguments = json_encode($arguments);
            }
            curl_setopt($curl_request, CURLOPT_POSTFIELDS, $arguments);
        }
        $result = curl_exec($curl_request);
        //echo "result: $result\n";
        if ($returnHeaders){
            //set headers from response
            list($headers, $content) = explode("\r\n\r\n", $result ,2);
            foreach (explode("\r\n",$headers) as $header){
                header($header);
            }
            //return the nonheader data
            return trim($content);
        }
        curl_close($curl_request);
        //decode the response from JSON
        $response = json_decode($result);
        return $response;
    }

    function login() {
        global $sugaruser, $sugarpwd, $oauthtoken;
        $url = "/oauth2/token";
        $oauth2_token_request = array(
            "grant_type" => "password",
            //client id/secret you created in Admin > OAuth Keys
            "client_id" => "sugar",
            "client_secret" => "",
            "username" => $sugaruser,
            "password" => $sugarpwd,
            "platform" => "colorfarmdatapull"
        );

        $oauth2_token_response = call($url, 'POST', $oauth2_token_request);
        //  catch errors
        if(property_exists($oauth2_token_response,'error')) {
            echo "Login Failed.\n";
            return false;
        }
        else {
            //  save access token
            $oauthtoken = $oauth2_token_response->access_token;
            echo "oauthtoken: $oauthtoken\n";
            return true;
        }
    }


    function main() {
        global $module, $batch_size;
        //  log into Sugar
        if(login()) {
            //  retrieve records in batches for deletion
            $offset = 0;
            $batch = 1;
            while($offset >= 0) {
                echo "Batch $batch: Retrieving $max records for deletion.\n";
                $args = array();
                $args['max_num'] = $batch_size;
                $args['offset'] = $offset;
                
                $e = call("/$module",'GET',$args);
                if(isset($e->error)) {
                    echo "Error: ".$e->error_message."\n";
                }
                else {
                    $offset = ($e->next_offset > 0 ? 0 : -1);
                    $records = $e->records;
                    $i = 1;
                    foreach($records as $r) {
                        echo "Delete $i/$max: ".$r->id."\n";
                        $d = call("/$module/".$r->id,'DELETE',null);
                        if(isset($d->error)) {
                            echo "Error: $d->error_message\n";
                        }
                        $i++;
                    }
                }
                $batch++;
            }
        }
        
        //  done!
        echo "Process complete.\n";
    }
    
    main();
?>
