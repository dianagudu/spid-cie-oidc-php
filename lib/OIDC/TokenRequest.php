<?php

/**
 * spid-cie-oidc-php
 * https://github.com/italia/spid-cie-oidc-php
 *
 * 2022 Michele D'Amico (damikael)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author     Michele D'Amico <michele.damico@linfaservice.it>
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 */

namespace SPID_CIE_OIDC_PHP\OIDC;

use SPID_CIE_OIDC_PHP\Core\JWT;
use GuzzleHttp\Client;

class TokenRequest
{
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function send($token_endpoint, $auth_code, $code_verifier, $refresh = false, $refresh_token = null)
    {
        $client_id = $this->config->rp_client_id;
        $client_assertion = array(
            "jti" => 'spid-cie-php-oidc_' . uniqid(),
            "iss" => $client_id,
            "sub" => $client_id,
            "aud" => $token_endpoint,
            "iat" => strtotime("now"),
            "exp" => strtotime("+180 seconds")
        );
        $client_assertion_type = "urn:ietf:params:oauth:client-assertion-type:jwt-bearer";
        $code = $auth_code;
        $grant_type = ($refresh && $refresh_token != null) ? 'refresh_token' : 'authorization_code';

        $crt = $this->config->rp_cert_public;
        $crt_jwk = JWT::getCertificateJWK($crt);

        $header = array(
            "typ" => "JWT",
            "alg" => "RS256",
            "jwk" => $crt_jwk,
            "kid" => $crt_jwk['kid'],
            "x5c" => $crt_jwk['x5c']
        );

        $key = $this->config->rp_cert_private;
        $key_jwk = JWT::getKeyJWK($key);

        $signed_client_assertion = JWT::makeJWS($header, $client_assertion, $key_jwk);

        $client = new Client([
            'allow_redirects' => true,
            'timeout' => 15,
            'debug' => false,
            'http_errors' => false
        ]);

        $data = array(
            'client_id' => $client_id,
            'client_assertion' => $signed_client_assertion,
            'client_assertion_type' => $client_assertion_type,
            'code' => $code,
            'code_verifier' => $code_verifier,
            'grant_type' => $grant_type,
        );

        if ($refresh && $refresh_token != null) {
            $data['refresh_token'] = $refresh_token;
        }

        $response = $client->post($token_endpoint, [ 'form_params' => $data ]);
        $code = $response->getStatusCode();
        if ($code != 200) {
            $reason = $response->getReasonPhrase();
            throw new \Exception($reason);
        }

        $this->response = json_decode((string) $response->getBody());

        // Check Response

        return $this->response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getAccessToken()
    {
        $access_token = $this->response->access_token;
        return $access_token;
    }

    public function getIdToken()
    {
        $id_token = $this->response->id_token;
        return $id_token;
    }
}
