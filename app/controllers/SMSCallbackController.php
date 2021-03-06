<?php

class SMSCallbackController extends \BaseController {

	public function callback()
	{
		try {
			Log::info('incomming', Input::all());
			$phone = Input::get('msisdn');
			if(!$phone)
				return;
			$user = User::where('mobile_number', $phone)->first();
			if(!$user) {
				throw new MobileNumberNotFoundException;
			}
			$text = Input::get('text');
			$keyword = Input::get('keyword');
			if($keyword === 'CERAMAH') {
				$parts = explode(';', substr($text, 8));
				// $parts[0] => location_id, $parts[1] => ceramah title, $parts[2] => ustadh name
				if(count($parts) !== 3) {
					throw new InvalidFormatException();
				}
				$location = Location::find($parts[0]);
				if(!$location) {
					throw new LocationNotFoundException();
				}

				$existing = Talk::where('user_id', $user->id)->where('status', '!=', 'Closed')->first();
				if($existing) {
					throw new AlreadyStreamingException();
				}

				$title = ucwords(trim($parts[2])) . ' - ' . ucwords(trim($parts[1])) . ' - ' . $location->name . ' - ' . date('j M y');

				$client = new Google_Client();
                $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
                $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
                $client->setScopes('https://www.googleapis.com/auth/youtube');
                $client->setRedirectUri(getenv('GOOGLE_REDIRECT_URL'));
				$client->refreshToken(file_get_contents(storage_path() . '/.google-refresh-token'));

                $youtube = new Google_Service_YouTube($client);

				$broadcastSnippet = new Google_Service_YouTube_LiveBroadcastSnippet();
			    $broadcastSnippet->setTitle($title);
			    $broadcastSnippet->setScheduledStartTime(date('Y', strtotime('+1 year')) . '-01-30T00:00:00.000Z');
			    $broadcastSnippet->setScheduledEndTime(date('Y', strtotime('+1 year')) . '-01-31T00:00:00.000Z');

			    $status = new Google_Service_YouTube_LiveBroadcastStatus();
    			$status->setPrivacyStatus('public');

    			$broadcastInsert = new Google_Service_YouTube_LiveBroadcast();
			    $broadcastInsert->setSnippet($broadcastSnippet);
			    $broadcastInsert->setStatus($status);
			    $broadcastInsert->setKind('youtube#liveBroadcast');

                $broadcastsResponse = $youtube->liveBroadcasts->insert('snippet,status',
                $broadcastInsert, array());
                
                $streamSnippet = new Google_Service_YouTube_LiveStreamSnippet();
                $streamSnippet->setTitle($title);
                
                $cdn = new Google_Service_YouTube_CdnSettings();
                $cdn->setFormat("360p");
                $cdn->setIngestionType('rtmp');
                
                $streamInsert = new Google_Service_YouTube_LiveStream();
                $streamInsert->setSnippet($streamSnippet);
                $streamInsert->setCdn($cdn);
                $streamInsert->setKind('youtube#liveStream');

                $streamsResponse = $youtube->liveStreams->insert('snippet,cdn', $streamInsert, array());

                $bindBroadcastResponse = $youtube->liveBroadcasts->bind(
                    $broadcastsResponse['id'],'id,contentDetails',
                    array(
                        'streamId' => $streamsResponse['id'],
                    ));

                Log::info('youtube-stream', json_decode(json_encode($streamsResponse), true));
                Log::info('youtube-broadcast', json_decode(json_encode($bindBroadcastResponse), true));

				$talk = Talk::create([
					'title' => $title,
					'location_id' => $location->id,
					'user_id' => $user->id,
                    'youtube_url' => $bindBroadcastResponse->getId(),
                    'rtmp_url' => $streamsResponse->getCdn()->getIngestionInfo()->getStreamName(),
				]);

				SMSService::StreamingReady($phone);
			} else if($keyword === 'BERHENTI') {
				$existing = Talk::where('user_id', $user->id)->where('status', '!=', 'Closed')->first();
				if(!$existing) {
					throw new NoActiveStreamException();
				}
				$existing->update(['status' => 'Closed']);
				SMSService::StreamClosed($phone, $existing->title);
			} else {
				throw new InvalidFormatException();
			}
		} catch (NoActiveStreamException $e) {
			SMSService::NoActiveStream($phone);
		}  catch (AlreadyStreamingException $e) {
			SMSService::AlreadyStreaming($phone, $existing->title);
		} catch (InvalidFormatException $e) {
			SMSService::InvalidFormat($phone);
		} catch (MobileNumberNotFoundException $e) {
			SMSService::MobileNumberNotRegistered($phone);
		} catch (LocationNotFoundException $e) {
			SMSService::LocationNotFound($phone, $parts[0]);
		}
	}

	public function __construct()
	{
		parent::__construct();
		View::share('controller', 'SMSCallback');
	}
}