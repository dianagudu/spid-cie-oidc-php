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

namespace SPID_CIE_OIDC_PHP\Federation;

use SPID_CIE_OIDC_PHP\Core\JWT;

/**
 *  Handle EntityStatement
 *
 *  [OpenID Connect Federation Entity Statement](https://openid.net/specs/openid-connect-federation-1_0.html#rfc.section.3.1)
 *
 */
class EntityStatement
{
    /**
     *  creates a new EntityStatement instance
     *
     * @param string $token entity statement JWS token 
     * @throws Exception
     * @return EntityStatement
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     *  creates the JWT to be returned from .well-known/openid-federation endpoint
     *
     * @param object $config base configuration
     * @param boolean $decoded if true returns JSON instead of JWS
     * @throws Exception
     * @return mixed
     */
    public static function makeFromConfig(object $config, $decoded = false)
    {
        $crt = $config->rp_cert_public;
        $crt_jwk = JWT::getCertificateJWK($crt);

        $payload = array(
            "iss" => $config->rp_client_id,
            "sub" => $config->rp_client_id,
            "iat" => strtotime("now"),
            "exp" => strtotime("+1 year"),
            "jwks" => array(
                "keys" => array( $crt_jwk )
            ),
            "authority_hints" => array(
                $config->rp_authority_hint
            ),
            "trust_marks" => array(),
            "metadata" => array(
                "openid_relying_party" => array(
                    "application_type" => "web",
                    "client_registration_types" => array( "automatic" ),
                    "client_name" => $config->rp_client_name,
                    "contacts" => array( $config->rp_contact ),
                    "grant_types" => array( "authorization_code" ),
                    "jwks" => array(
                        "keys" => array( $crt_jwk )
                    ),
                    "redirect_uris" => array( $config->rp_client_id . '/oidc/redirect' ),
                    "response_types" => array( "code" ),
                    "subject_type" => "pairwise"
                )
            )
        );

        $header = array(
            "typ" => "entity-statement+jwt",
            "alg" => "RS256",
            "kid" => $crt_jwk['kid']
        );

        $key = $config->rp_cert_private;
        $key_jwk = JWT::getKeyJWK($key);
        $jws = JWT::makeJWS($header, $payload, $key_jwk);

        return $decoded ? json_encode($payload) : $jws;
    }

    /**
     *  verify token and returns parsed json payload
     *
     * @param string $token entity statement JWS token
     * @throws Exception
     * @return mixed
     */
    public function parse() {
        if(!JWT::isValid($this->token)) {
            throw new \Exception("entity statement non valid");
        }
        return JWT::getJWSPayload($this->token);
    }
}
