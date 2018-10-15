<?php
require __DIR__ . "/vendor/autoload.php";
use Twilio\Rest\Client;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

/**
 * Connects to PostgreSQL database
 */
function connectDatabase()
{
    $databaseName = getenv(DB_DATABASE);
    $databaseUserName = getenv(DB_USERNAME);
    $databasePassword = getenv(DB_PASSWORD);

    $databaseConnection = pg_connect("host=localhost dbname=$databaseName user=$databaseUserName password=$databasePassword");

    if (!$databaseConnection) {
        die("Error in connection: " . pg_last_error());
    }

    return $databaseConnection;
}

/**
 * check if channel is live
 * if it's live, call the sendSms &
 * saveVideoId functions.
 * 
 * @return void
 */
function checkIfChannelIsLive()
{
    $channelID = "UCkAGrHCLFmlK3H2kd6isipg";
    $googleApiKey = getenv(GOOGLE_API_KEY);
    $channelAPI = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=".$channelID."&type=video&eventType=live&key=".$googleApiKey;
    
    $channelInfo = json_decode(file_get_contents($channelAPI));

    $channelStatus = $channelInfo->pageInfo->totalResults > 0 ? true : false;
    $videoId = $channelInfo->items[0]->id->videoId;

    $previousVideoId = getPreviousVideoID();

    if ($channelStatus && $previousVideoId !== $videoId) {
        sendSms();
        saveVideoId($videoId);
    }
}


/**
 * Fetch the last saved video id
 * from the database.
 * 
 * @return String the previous video id for which an sms was sent
 */
function getPreviousVideoId()
{
    $databaseConnection = connectDatabase();

    $query = "SELECT video_id FROM videos ORDER BY ID DESC LIMIT 1";

    $queryResult = pg_query($databaseConnection, $query);
    
    $previousVideoId = pg_fetch_array($queryResult)[0];

    pg_close($databaseConnection);

    return $previousVideoId;
}

/**
 * Loops through our array of numbers
 * and sends messages.
 * 
 * @return void
 */
function sendSms()
{
    $twilioAccountSid = getenv(TWILIO_SID);
    $twilioAuthToken = getenv(TWILIO_TOKEN);
    $client = new Client($twilioAccountSid, $twilioAuthToken);
    $myTwilioNumber = "+18558371886";

    $subscribers = getSubscribers();
    
    foreach ($subscribers as $subscriber) {
        $client->messages->create(
            // Where to send a text message
            $subscriber,
            array(
                "from" => $myTwilioNumber,
                "body" => "Hey! the channel is live!"
            )
        );
    }
}

/**
 * Fetch the list of subscribers' numbers
 * saved in the database
 * 
 * @return Array subscribers' mobile numbers
 */
function getSubscribers()
{
    $databaseConnection = connectDatabase();
    $query = "SELECT mobile_numbers FROM subscribers";

    $queryResult = pg_query($databaseConnection, $query);

    $subscribersNumbers = [];

    while ($row = pg_fetch_row($queryResult)) {
        array_push($subscribersNumbers, $row[0]);
    }

    pg_close($databaseConnection);

    return $subscribersNumbers;
}

/**
 * Save the current video Id to the database
 * 
 * @param String $videoId - current video id
 * 
 * @return void
 */
function saveVideoId($videoId) {
    $databaseConnection = connectDatabase();
    $today = date('Y-m-d h:i:sa');

    $query = "INSERT INTO videos (video_id, created_at) VALUES('$videoId', '$today')";

    pg_query($databaseConnection, $query);

    pg_close($databaseConnection);
}


checkIfChannelIsLive();
