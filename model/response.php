<?php
// Response Object
class response {
  private $_statusCode;
  private $_success;
	private $_category;
  private $_bookInfo;
  private $_messages = array();
	private $_httpStatusCode;
	private $_toCache = false;
	private $_responseData = array();

  //define setStatusCode
  public function setStatusCode($statusCode){
    $this->_statusCode = $statusCode;
  }

	// define setSuccess flag method - true or false
	public function setSuccess($success) {
		$this->_success = $success;
	}

  //Set Category
  public function setCategory($category) {
	$this->_category = $category;
	}

  //Set bookInfo
  public function setBookInfo($bookInfo) {
    $this->_bookInfo = $bookInfo;
  }

  // define addMessage method - can add any error or information info
  public function addMessage($message) {
  	$this->_messages[] = $message;
  }

	// define setHttpStatusCode method - numeric status code
	public function setHttpStatusCode($httpStatusCode) {
		$this->_httpStatusCode = $httpStatusCode;
	}

	// define toCache flag method - true or false
	public function toCache($toCache) {
		$this->_toCache = $toCache;
	}

	// define the send method - this will send the built response object to the browser
	// in json format
	public function send() {
		// set response header contact type to json utf-8
		header('Content-type:application/json;charset=utf-8');

		// if response is cacheable then add http cache-control header with a timeout of 60 seconds
		// else set no cache
		if($this->_toCache == true) {
			header('Cache-Control: max-age=60');
		}
		else {
			header('Cache-Control: no-cache, no-store');
		}

		// if response is not set up correctly, e.g. not numeric in status code or success not true or false
		// send a error response
		if(!is_numeric($this->_httpStatusCode) || ($this->_success !== false && $this->_success !== true )) {
			// set http status code in response header
			http_response_code(500);
			// set statusCode in json response
			$this->_responseData['statusCode'] = 500;
			// set success flag in json response
			$this->_responseData['success'] = false;
			// set custom error message
		  $this->addMessage("Response creation error");
			// set messages in json response
	    $this->_responseData['messages'] = $this->_messages;
		}
		else {
			// set http status code in response header
			http_response_code($this->_httpStatusCode);
			// set statusCode in json response
			$this->_responseData['statusCode'] = $this->_httpStatusCode;
			// set success flag in json response
			$this->_responseData['success'] = $this->_success;
      // set subcategory in json response
      if(isset($this->_bookInfo)){
        $this->_responseData['bookInfo'] = $this->_bookInfo;
      }
      // set messages
      if(sizeof($this->_messages)>0){
        $this->_responseData['messages'] = $this->_messages;
      }
		}

		// encode the responseData array to json response output
		echo json_encode($this->_responseData);
	}

}
