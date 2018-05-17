<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://api.yige.ai/v1/entities');
curl_setopt($ch, CURLOPT_KEYPASSWD, '04A1B38FF10F3561F1B04B33FC51281D' );
curl_setopt($ch, CURLOPT_USERPWD,"Authorization:04A1B38FF10F3561F1B04B33FC51281D");
//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
var_dump($response);