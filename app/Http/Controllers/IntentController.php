<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Twilio\Rest\Client;
use Twilio\Twiml;

use Illuminate\Support\Facades\Cache;

class IntentController extends Controller
{
    public function processIntent(Request $request)
    {
    	$request_id = $request->input('request.requestId');
    	$intent_name = $request->input('request.type') === 'LaunchRequest' ? 'LaunchRequest' : $request->input('request.intent.name');

    	switch($intent_name)
    	{
    		case 'LaunchRequest':
		    	return response()->json([
		    		'version' => '1.0',
		    		'outputSpeech' => [
		    			'type' => 'PlainText',
		    			'text' => 'Welcome to the Twilio Alexa assistant. Say send a voice message to start.',
		    		],
		    		'shouldEndSession' => true,
		    	]);
    		break;
    		case 'SelectIntent':
    			$phone_number = $request->input('request.intent.slots.number.value');

		    	return response()->json([
		    		'version' => '1.0',
		    		'outputSpeech' => [
		    			'type' => 'PlainText',
		    			'text' => 'fake',
		    		],
		    		'reprompt' => [
		    			'outputSpeech' => [
		    				'type' => 'PlainText',
		    				'text' => 'What message would you like to send?',
		    			],
		    		],
		    		'shouldEndSession' => false,
		    		'sessionAttributes' => [
		    			'phoneNumber' => $phone_number,
		    		],
		    	]);
    		break;
    		case 'MessageIntent':
    			// we grab the auth info from the environment file
    			$account_sid = env('TWILIO_ACCOUNT_SID');
    			$auth_token = env('TWILIO_AUTH_TOKEN');
    			$from_number = env('TWLIO_NUMBER');

    			// we grab to `To` number from the session data Alexa sends us
    			$to_number = $request->input('request.sessionAttributes.phoneNumber');

    			// message
    			$message = $request->input('request.intent.slots.message.value');

	   			$client = new Client($account_sid, $auth_token);
				$call = $client->account->calls->create(  
				    $to_number,
				    $from_number,
				    [
				    	'url' => route('twilio.callback'),
				    ],
				);

				// now cache the call sid and message to say
				Cache::set($call->sid, $message);

		    	return response()->json([
		    		'version' => '1.0',
		    		'outputSpeech' => [
		    			'type' => 'PlainText',
		    			'text' => 'Sending ' . $message . ' to ' . $to_number,
		    		],
		    		'shouldEndSession' => true,
		    	]);
			break;
    	}
    }

    public function processCall(Request $request)
    {
    	// get twilio call sid
    	$id = $_POST['CallSid'];

    	$message = Cache::get($id);

    	$twiml = new Twiml();
    	$twiml->say($message, [
    		'voice' => 'alice',
    	]);

	    $response = Response::make($twiml, 200);
	    $response->header('Content-Type', 'text/xml');
	    return $response;
    }
}
