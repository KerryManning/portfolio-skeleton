<?php

require "vendor/autoload.php";
require "classes/project-data.php";

// use Slim request and response classes
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// create custom configuration settings for slim app
// $config['displayErrorDetails'] = true;
$config['db']['host']   = "localhost";
$config['db']['user']   = "root";
$config['db']['pass']   = "root";
$config['db']['dbname'] = "database";

// create app
$app = new \Slim\App(["settings" => $config]);
// get dependency injection container add-on twig-views
$container = $app->getContainer();
// register twig component on conatiner
$container['view'] = function ($container) {
  $view = new \Slim\Views\Twig('templates', [
    // remove for publish
    // 'cache' => false,
    // 'debug' => true
  ]);
  // instantiate and add Slim specific extension
  $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
  $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));

  // necessary to use dump(), remove for publish
  // $view->addExtension(new Twig_Extension_Debug());

  return $view;
};

// register PDO component on container
$container['db'] = function($container) {
  try {
    $db = $container['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'], $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
  } catch (Exception $e) {
    echo "<p>Unable to connect to database.</p>";
    echo $e->getMessage();
    exit;
  }
};

// render twig templates in routes
$app->get('/', function ($request, $response) {
  return $this->view->render($response, 'index.twig');
})->setName('home');

$app->get('/work', function ($request, $response, $args) {
  $data = new ProjectData;
  $data->setDb($this->db);
  $data->query("SELECT title, thumb, thumb_alt FROM projects");
  $thumbnail_array = $data->thumbnail_array_results();
  return $this->view->render($response, 'work.twig', [
    'thumbnail_array' => $thumbnail_array
    ]);
})->setName('work');

$app->get('/work/{title}', function ($request, $response, $args) {
  $title = strtolower(str_replace("-", " ", $args['title']));
  $data = new ProjectData;
  $data->setDb($this->db);
  $data->query("SELECT title, description, body, img, img_alt, tech_tags, website FROM projects WHERE LOWER(title) = ? ");
  $data->bind(1, $title, PDO::PARAM_STR);
  $project_array = $data->project_array_results();
  return $this->view->render($response, 'project.twig', [
        'id_title' => $title,
        'project' => $project_array
    ]);
})->setName('project');

$app->get('/contact', function ($request, $response, $args) {
  return $this->view->render($response, 'contact.twig');
})->setName('contact');

$app->get('/contact/{status}', function ($request, $response, $args) {
  return $this->view->render($response, 'contact.twig',[
    'status' => $args['status']
    ]);
})->setName('contact-thanks');

$app->post('/contact', function ($request, $response, $args) {
  $data = $request->getParsedBody();
  $name = filter_var($data['fullname'], FILTER_SANITIZE_STRING);
  $email = filter_var($data['email'], FILTER_SANITIZE_STRING);
  $msg = filter_var($data['message'], FILTER_SANITIZE_STRING);

  if($name=="" OR $email=="" OR $msg=="") {
    $error_message = "PLEASE COMPLETE ALL FIELDS";
  }

  if(!isset($error_message) && $data["address"]!="") {
    $error_message = "BAD FORM INPUT";
  }

  require "vendor/phpmailer/phpmailer/PHPMailerAutoload.php";
  $mail = new PHPMailerOAuth;
  if (!isset($error_message)) {
    // concatenate form inputs into $email_body variable
    $email_body = "";
    $email_body .= "Name: " . $name . ". " . "\n";
    $email_body .= "Email Address: " . $email .  ". " ."\n";
    $email_body .= "Message: " . $msg . ". " . "\n";

    // copy and customise code from phpmailer github
    $mail->isSMTP();
    // $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';
    $mail->Host = "smtp.gmail.com";
    $mail->Port = 587;
    $mail->SMPTSecure = "tls";
    $mail->SMTPAuth = true;
    $mail->AuthType = 'XOAUTH2';
    $mail->oauthUserEmail = "you@gmail.com";
    // insert values generated with gmail api
    $mail->oauthClientId = "*******";
    $mail->oauthClientSecret = "*******";
    $mail->oauthRefreshToken = "********";

    $mail->setFrom("you@gmail.com");
    $mail->addAddress("someone@mail.com");     // add a recipient
    
    $mail->isHTML(true);
    $mail->Subject = "Contact from " . $name;
    $mail->Body    = $email_body;

    // check form email has sent redirect to thanks page
    if($mail->send()) {
      header("location:/contact/thanks");
      exit;
    }
    // set error message if email isn't sent
    $error_message = "Message could not be sent.";
    $error_message .= "Mailer Error: " . $mail->ErrorInfo;
  }

   return $this->view->render($response, 'contact.twig', [
    'name' => $name,
    'error_message' => $error_message,
    'status' => $status
    ]);
})->setName('contact-post');

// run app
$app->run();

?>
