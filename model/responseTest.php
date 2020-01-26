<?php

  require_once('response.php');

  $response = new response();
  $response->setSuccess(true);
  $response->setHttpStatusCode(200);

  //build bookInfo object
  $bookInfoDetails = array();
  $bookInfoDetails['id'] = 1;
  $bookInfoDetails['category'] = "Programming";
  $bookInfoDetails['title'] = "Java Programming";
  $bookInfoDetails['pageCount'] = 430;
  $bookInfoDetails['language'] = "English";
  $bookInfoDetails['etag'] = "4444444444";

  $response->setBookInfo($bookInfoDetails);

  $response->send();
