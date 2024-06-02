<?php
require __DIR__ . '/vendor/autoload.php';

session_start();


// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
$client->addScope(Google_Service_Calendar::CALENDAR);

if (isset($_GET['logout'])) {
    unset($_SESSION['access_token']);
    session_destroy();
    header('Location: index.php');
    exit();
}

include('includes/header.html');

if (!isset($_SESSION['access_token'])) {
    
    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);

        echo $token.'true';
        if (array_key_exists('refresh_token', $token)) {
            $_SESSION['refresh_token'] = $token['refresh_token'];
        } elseif (isset($_SESSION['refresh_token'])) {
            $client->refreshToken($_SESSION['refresh_token']);
            $token = $client->getAccessToken();
        }

        $_SESSION['access_token'] = $token;
        header('Location: index.php');
        exit();
    } else {
        $authUrl = $client->createAuthUrl();
        echo "<a href='" . filter_var($authUrl, FILTER_SANITIZE_URL) . "'>Connect to Google Calendar</a>";
    }
} else {
    $client->setAccessToken($_SESSION['access_token']);

    // Refresh the token if it's expired
    if ($client->isAccessTokenExpired()) {
        if (isset($_SESSION['refresh_token'])) {
            $client->fetchAccessTokenWithRefreshToken($_SESSION['refresh_token']);
            $_SESSION['access_token'] = $client->getAccessToken();
        } else {
            // Handle the case where there is no refresh token
            unset($_SESSION['access_token']);
            header('Location: index.php');
            exit();
        }
    }

    $service = new Google_Service_Calendar($client);

    // Displays event data
    $calendarId = 'primary';
    $optParams = array(
        'maxResults' => 10,
        'orderBy' => 'startTime',
        'singleEvents' => true,
        'timeMin' => date('c'),
    );
    $results = $service->events->listEvents($calendarId, $optParams);
    $events = $results->getItems();

    echo "<h1>Upcoming Events</h1>";
    if (empty($events)) {
        echo "<p>No upcoming events found.</p>";
    } else {
        echo "<ul>";
        foreach ($events as $event) {
            $start = $event->start->dateTime;
            if (empty($start)) {
                $start = $event->start->date;
            }
            echo "<li>" . htmlspecialchars($event->getSummary()) . " (" . htmlspecialchars($start) . ") 
        }
        echo "</ul>";
    }

  
}

include('includes/footer.html');
?>
