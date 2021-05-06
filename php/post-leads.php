<?php
  define("CONNECT_ENDPOINT", "https://connect.contactstate.com/leads");
  define("CONNECT_API_KEY", "YOUR-API-KEY-HERE");

  // Form POST handler
  if($_POST) {
    $headers = array(
      "Content-type: application/json",
      "Authorization: Bearer " . CONNECT_API_KEY
    );
    $payload = json_encode(array(
      'first_name' => $_POST['first_name'],
      'last_name'  => $_POST['last_name'],
      'email'      => $_POST['email'],
      'home_phone' => $_POST['home_phone'],
      // map other API fields to your form here
    ));

    // Curl configuration
    $curl = curl_init(CONNECT_ENDPOINT);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $response_headers = array();
    curl_setopt($curl, CURLOPT_HEADERFUNCTION,
      function($ch, $header) use (&$response_headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) return $len;
        $response_headers[trim($header[0])] = trim($header[1]);
        return $len;
      }
    );
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    $json_response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = json_decode($json_response, true);

    $success = null;
    $errors = array();

    // Valid response
    if (($status >= 200) && ($status <= 299)) {
      $lead_id = $response['id'];
      $success = "Successfully sent lead to Contact State Connect. The Connect Lead ID is " . $lead_id;
    }
    // Validation errors
    else if ($status == 400) {
      $errors['base'] = "There was a problem with the request to Contact State Connect.";

      foreach ($response['errors'] as $error) {
        if ($error['code'] == 'validation') {
          $errors[$error['param']] = $error['message'];
        }
      }
    } 
    // Auth error
    else if ($status == 401) {
      $errors['base'] = "API key invalid";
    } 
    // Rate limiting error
    else if ($status == 429) {
      $next_retry_time = $response_headers['RateLimit-Reset'];
      $errors['base'] = "Rate limited. Please try submitting again at " . gmdate("Y-m-d\TH:i:s\Z", $next_retry_time);
    } 
    // Internal server error / maintenance
    else if (($status >= 500) && ($status <= 599)) {
      $errors['base'] = "Could not send to Contact State Connect. Please try again later.";
    } else {
      $errors['base'] = "An unknown error occured.";
    }

    curl_close($curl);
  }
?>

<html>
  <head><title>Contact State Connect Example</title></head>
<body>

<?php
  if (!empty($errors)) {
    echo "There were problems with the form submission:" . '<br />';

    foreach ($errors as $field => $message) {
      echo "$field: $message" . '<br />';
    }
  }

  if (!is_null($success)) {
    echo $success;
  }
?>

<form method="POST" action="<?PHP echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" accept-charset="UTF-8">
  <p>
    <label>First name<strong>*</strong><br />
    <input type="text" name="first_name" value="<?php if(isset($_POST['first_name'])) echo htmlspecialchars($_POST['first_name']); ?>"></label>
  </p>
  <p>
    <label>Last name<strong>*</strong><br />
    <input type="text" name="last_name" value="<?php if(isset($_POST['last_name'])) echo htmlspecialchars($_POST['last_name']); ?>"></label>
  </p>
  <p>
    <label>Email<strong>*</strong><br />
    <input type="text" name="email" value="<?php if(isset($_POST['email'])) echo htmlspecialchars($_POST['email']); ?>"></label>
  </p>
  <p>
    <label>Phone<br />
    <input type="text" name="home_phone" value="<?php if(isset($_POST['home_phone'])) echo htmlspecialchars($_POST['home_phone']); ?>"></label>
  </p>
  <p>
    <input type="submit" name="submit" value="Send lead">
  </p>
</form>

</body>
</html>
