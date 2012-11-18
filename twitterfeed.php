<?php

/*

	Author: Barney Staddon
	
	To use, make a call to checkTweetCache in your page.
	This will update the cached tweets if necessary.
	Then call getLatestTweet() or getLatestTweets() to display as required.             
	
*/	
		   

function checkTweetCache()
{
	//change this to the username/s you wish to display tweets from   
	$tweeters = array("tweeterusername1", "tweeterusername1", "tweeterusername1");
		
	$tweetResults = 0;
	$storedTweetLimit = 20;
	$mostRecentTweetTimes = array();
	$tweetsDataArray = array();
    $updatedTweetsDataArray = array(); 		
		
	$timeFile = "time.txt";
	$ft = fopen($timeFile, 'r');
	$lastCacheTime = fread($ft, filesize($timeFile));
	fclose($ft);
	
	$now = time();
				
	if($now > $lastCacheTime + 86400) //If call not made for specified period, currently 1 day 
	{
			
		$count1 = sizeof($tweeters);
			
		//for each tweeter
		for($counter1 = 0; $counter1 < $count1; $counter1 = $counter1 + 1)
		{
			//store most recent tweet creation time and xml from existing stored tweets
			$tweetsStr = file_get_contents($tweeters[$counter1].".xml");
			$tweetsXml = simplexml_load_string($tweetsStr);				
			$tweetTime = $tweetsXml->status[0]->created_at;    
								
			array_push($mostRecentTweetTimes,strtotime($tweetTime));   
			array_push($tweetsDataArray,$tweetsStr);		
				
			$ch = curl_init("https://api.twitter.com/1/statuses/user_timeline.xml?screen_name=".$tweeters[$counter1]);
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$tweetsData = curl_exec($ch);
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE); //returns http status code for the last curl request
			curl_close($ch);
								
			if($http_status == '200') //if we have a true result   
			{
				$tweetsDataXml = simplexml_load_string($tweetsData);
					
				//get number of tweets
				$numOfTweets = count($tweetsDataXml->status);
				$newTweets = " "; 
										
				//loop through all tweets from bottom upwards
				for($counter2 = $numOfTweets - 1; $counter2 >= 0; $counter2 = $counter2 - 1)
				{
					//if this tweet is newer than our most recent stored tweet
					if(strtotime($tweetsDataXml->status[$counter2]->created_at) > $mostRecentTweetTimes[$counter1])
					{
						//get id of new tweet
						$tweetID = $tweetsDataXml->status[$counter2]->id; 
							
						//get all nodes for new tweet only
						$nodes = $tweetsDataXml->xpath('/statuses/status[id = '.$tweetID.']');
						$result = '';
						foreach ( $nodes as $node )
						{
							$result .= $node->asXML()."\n";
						}
													
						$newTweets = $result.$newTweets;
					}						
				}
							
				//get ref to stored tweets 
				$tempTweetsXML = $tweetsDataArray[$counter1];
							
				//if we have new tweets 
				if(strlen($newTweets) > 1)
				{
					//get pos of statuses tag in stored tweets;
					$match = '<statuses type="array">';
					$matchPos = stripos($tempTweetsXML,$match);
							
					//get xml after statuses tag (i.e old tweets)
					$restOfTweets = substr($tempTweetsXML,$matchPos + strlen($match));
						
					//insert new tweet as top tweet in stored tweets   
					$tempTweetsXML = substr_replace($tempTweetsXML,$match.$newTweets,$matchPos);
					$updatedTweetsXML = $tempTweetsXML."\n".$restOfTweets;
				}		
				else
				{
					$updatedTweetsXML = $tempTweetsXML;	
				}	
					
				array_push($updatedTweetsDataArray,$updatedTweetsXML);
							
				$tweetResults ++;		
			}
			else
			{
				return 0; //return on first failed poll.				
			}				
		}
			
		/********************************************************************************************************/
		/*
		/* Here we first check the updated tweet docs aren't too long. Then we write all the temp tweet docs in
		/* $updatedTweetsDataArray to the respective cached tweeter.xml docs.
        /* 
		/* N.B these are only written if we get a successful result from Twitter for all usernames.
        /* Also note, the cached XML docs will be overwritten even if there are no new tweets in the result sets.
        /* This ensures that if only some of the feeds contain new tweets, they will still be cached. 
		/*
		/*********************************************************************************************************/
			
			
		if($tweetResults == sizeof($tweeters)) //double check, not strictly neccesary!  
		{
			$count3 = sizeof($updatedTweetsDataArray);
				
			//for each tweet set  
			for($counter3 = 0; $counter3 < $count3; $counter3 = $counter3 + 1)
			{
			    //check length  
				$checkXml = simplexml_load_string($updatedTweetsDataArray[$counter3]);
					
				$numOfUpdatedTweets = count($checkXml->status);
					
				$tweetsTruncated = 0;
					
				if($numOfUpdatedTweets > $storedTweetLimit)
				{
					//get time of first extraneous status
					$extraTweetTimestamp = strtotime($checkXml->status[$storedTweetLimit]->created_at);
												
					//rebuild tweets
					//loop through all tweets and append to string if less than  
					$trucatedTweets = "";
												
					for($counter4 = 0; $counter4 < $numOfUpdatedTweets; $counter4 = $counter4 + 1)
					{
						//if tweet more recent than extra one, we keep it 							
						if(strtotime($checkXml->status[$counter4]->created_at) > $extraTweetTimestamp)
						{
							$keepTweetId = $checkXml->status[$counter4]->id;
											
							//get all nodes for new tweet only
							$newNodes = $checkXml->xpath('/statuses/status[id = '.$keepTweetId.']');
							$newResult = '';
							foreach ( $newNodes as $newNode )
							{
								$newResult .= $newNode->asXML()."\n";
							}
																					
							$trucatedTweets = $trucatedTweets.$newResult;								
						}							
					}
											
					$tweetsTruncated = 1;
				}	
					
				$lastTweetsFile = $tweeters[$counter3].".xml";
				$fd = fopen($lastTweetsFile, 'w') or die("can't open file");
					
				if($tweetsTruncated == 1)
				{
					fwrite($fd, '<?xml version="1.0" encoding="UTF-8"?><statuses type="array">'.$trucatedTweets.'</statuses>'); //this needs to be a string
				}	
				else
				{
					fwrite($fd, $updatedTweetsDataArray[$counter3]); //this needs to be a string	
				}
					
				fclose($fd);
			}
								
			//finally note the time of the last successful update  
			$tt = time();
			$lastStoredTimeFile = "time.txt";
			$fc = fopen($lastStoredTimeFile, 'w') or die("can't open file");
			fwrite($fc, $tt);
			fclose($fc);
				
			return 1; //tweets updated 
		}

	return -1; //something's gone wrong		
			
	}	
						
return 2; //use cache;	

}


function getAllTweets($username)
{
	checkTweetCache();  
	
	//Read required tweets from cache regardless of above call  
	$storeFile = $username.".xml";
	$ft = fopen($storeFile, 'r');
	$storedFeed = fread($ft, filesize($storeFile));
	fclose($ft);
		
	echo parseFeedForAllTweets($storedFeed);
	//echo $storedFeed;	
}

function getLatestTweet($username)
{
	checkTweetCache();  
	
	//Read required tweets from cache regardless of above call  
	$storeFile = $username.".xml";
	$ft = fopen($storeFile, 'r');
	$storedFeed = fread($ft, filesize($storeFile));
	fclose($ft);
	
	echo parseFeedForLatestTweet($storedFeed);
}

function parseFeedForAllTweets($feed)
{
	$tweetsDataXml = simplexml_load_string($feed);
	
	$tweets = "<ul>";
	
	for ($i = 0; $i < 6; $i++) //just shows top 6 tweets
	{
		$tweets = $tweets."<li class='tweet'>";
		$tweet = $tweetsDataXml->status[$i]->text;
		$tweet = makeClickableLinks($tweet);
		$tweets = $tweets.$tweet;
			
		$date = $tweetsDataXml->status[$i]->created_at;
		$date = time_difference($date);
		$tweets = $tweets."<div class='date'>".$date;
		$tweets = $tweets."</div>";
		$tweets = $tweets."</li>";
	}	
	
	$tweets = $tweets."</ul>";
	return $tweets;
}

function parseFeedForLatestTweet($feed)
{
	$tweetsDataXml = simplexml_load_string($feed);
	
	$tweets = "";	
	$tweet = $tweetsDataXml->status[0]->text;	
	$tweet = makeClickableLinks($tweet);
	$tweets = $tweets."<p id='status'>".$tweet;
	$tweets = $tweets."</p>";
	$date = $tweetsDataXml->status[0]->created_at;
	$date = time_difference($date);
	$tweets = $tweets."<p>".$date."</p>";
			
	return $tweets;
}

function makeClickableLinks($text) {

	$text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $text);
	$text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $text);
	$text = preg_replace("/@(\w+)/", "<a href=\"http://www.twitter.com/\\1\" target=\"_blank\">@\\1</a>", $text);
	$text = preg_replace("/#(\w+)/", "<a href=\"http://search.twitter.com/search?q=\\1\" target=\"_blank\">#\\1</a>", $text);
 
	return $text;
}

//This function returns the amount of time since the the displayed tweet was posted        
function time_difference($date)
{
    if(empty($date)) {
        return "No date provided";
    }
    
    $periods         = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
    $lengths         = array("60","60","24","7","4.35","12","10");
    
    $now             = time();
    $unix_date         = strtotime($date);
    
       // check validity of date
    if(empty($unix_date)) {  
        return "Bad date";
    }
 
    // is it future date or past date
    if($now > $unix_date) {  
        $difference     = $now - $unix_date;
        $tense         = "ago";
        
    } else {
        $difference     = $unix_date - $now;
        $tense         = "from now";
    }
    
    for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
        $difference /= $lengths[$j];
    }
    
    $difference = round($difference);
    
    if($difference != 1) {
        $periods[$j].= "s";
    }
    
    return "$difference $periods[$j] {$tense}";
}

?>
