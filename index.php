<?php

// Performs lookups based on user input of desired hashtags.
// Accepts options for Auto or Draft mode posts
// Allows user to post all of their Instagram images, or filter by specific hashtag
// Allows user to import posts with a specific hashtag by any user

// This code was written prior to learning how to properly cache data from an OAuth API
// not recommended for production
// Requires a MySQL Database


// ENTER USER TOKEN WHICH WILL DO ALL API CALLS

$ThisUserToken = "XXXXXX.XXXXXX.XXXXXXX";

$VarTimestamp = time();


  $dbhost = 'localhost';
  $dbuser = 'usernamehere';
  $dbpass = 'XXXXXXXXXXXXXXXXX';
  $dbname = 'databasenamehere';

  global $dbhost, $dbuser, $dbpass, $dbname



$conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
mysql_select_db($dbname);

	$ThisUsersUPL1NKID = $_GET['uid'];

  // Allow user to manually trigger import, or run in default Cron job mode

	if ($ThisUsersUPL1NKID <> "") {
		$InstagramSQL = "SELECT * FROM USERS WHERE (ID = " . $ThisUsersUPL1NKID . ")";
	} else {
		$InstagramSQL = "SELECT * FROM USERS WHERE (InstagramImport >= 'a')";
	}

		$TheResults = mysql_query($InstagramSQL);
		while ($TheFields = mysql_fetch_assoc($TheResults)) {

			$TheUserID = $TheFields['ID'];
			$TheUserName = $TheFields['UserName'];
			$VarInstagram = $TheFields['InstagramImport'];
			$VarInstagramPostMode = $TheFields['InstagramPostMode'];
			$ThisUsersIGID = $TheFields['InstagramUSERID'];
			$ThePageID = $TheFields['InstagramPageID'];
			$VarHashFilter = $TheFields['InstagramHash'];
			$VarHashFilterMinID = $TheFields['InstagramHashMinID'];
			$VarHashFilterSearch1 = $TheFields['InstagramHashSearch1'];
			$VarHashFilterSearch1MinID = $TheFields['InstagramHashSearch1MinID'];
			$VarHashFilterSearch2 = $TheFields['InstagramHashSearch2'];
			$VarHashFilterSearch2MinID = $TheFields['InstagramHashSearch2MinID'];
			$VarHashFilterSearch3 = $TheFields['InstagramHashSearch3'];
			$VarHashFilterSearch3MinID = $TheFields['InstagramHashSearch3MinID'];

			// get their userid
			if ($ThisUsersIGID==""||$ThisUsersIGID==ISNULL) {
				$ThisUsersIGID = GetIGUserID($VarInstagram,$TheUserID);
			}

			if ($VarInstagramPostMode==""||$VarInstagramPostMode==ISNULL) {
				$VarInstagramPostMode = 3;
			}

             // Default search based on user's own IG account
						 HashFilterSearch($VarHashFilter,$VarInstagram,1,$ThisUsersIGID,$VarHashFilterMinID,"InstagramHashMinID",$TheUserID,$VarInstagramPostMode,$ThePageID);

            // optional searches

							if ($VarHashFilterSearch1 >= "a") {
								 HashFilterSearch($VarHashFilterSearch1,$VarInstagram,2,0,$VarHashFilterSearch1MinID,"InstagramHashSearch1MinID",$TheUserID,$VarInstagramPostMode,$ThePageID);
							}

							if ($VarHashFilterSearch2 >= "a") {
								 HashFilterSearch($VarHashFilterSearch2,$VarInstagram,2,0,$VarHashFilterSearch2MinID,"InstagramHashSearch2MinID",$TheUserID,$VarInstagramPostMode,$ThePageID);
							}

							if ($VarHashFilterSearch3 >= "a") {
								 HashFilterSearch($VarHashFilterSearch3,$VarInstagram,2,0,$VarHashFilterSearch3MinID,"InstagramHashSearch3MinID",$TheUserID,$VarInstagramPostMode,$ThePageID);
							}

							// timestamp with last search date
							UpdateTimestamp($VarTimestamp,$TheUserID);

		}


mysql_close($conn);


////////////////////////////////////////
// Functions to find IG posts, clean them up, and insert into DB
////////////////////////////////////////

function fetchData($url){
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}


function getHashtags($string) {
    $hashtags= FALSE;
    preg_match_all("/(#\w+)/u", $string, $matches);
    if ($matches) {
        $hashtagsArray = array_count_values($matches[0]);
        $hashtags = array_keys($hashtagsArray);
    }


  foreach($hashtags as $val) {
    $TagVal = str_replace("#", "", $val);

    $VarTheTagsString = $VarTheTagsString . $TagVal . ", ";
  }

  $VarTheTagsString = rtrim($VarTheTagsString, ', ');
    return $VarTheTagsString;
}


function HashFilterSearch($HashSearch,$InstagramUsername,$VarMode,$ThisUsersIGID,$ThisMinID,$VarTheField,$TheUserID,$VarInstagramPostMode,$ThePageID) {

  global $ThisUserToken;

  $VarHashFilterWHashSearch1 = "#" . $HashSearch;

  $VarLoopCounter = 1;
  $VarProceed = 1;

  if ($ThisMinID >= "1" ){

    if ($VarMode==1) {
      $appendThisMinIDVar = "&min_id=" . $ThisMinID;
    } else {
      $appendThisMinIDVar = "&min_tag_id=" . $ThisMinID;
    }

  } else {
    $appendThisMinIDVar = "";
  }



  if ($VarMode == 1) {
    $VarTheURL = "https://api.instagram.com/v1/users/" . $ThisUsersIGID . "/media/recent?access_token=" . $ThisUserToken . $appendThisMinIDVar ;
  } else {
    $VarTheURL = "https://api.instagram.com/v1/tags/" . $HashSearch . "/media/recent?access_token=" . $ThisUserToken . $appendThisMinIDVar;
  }

    $result = fetchData($VarTheURL);
    $result = json_decode($result);

    $TheRealMinID = $result->pagination->min_tag_id;

    foreach ($result->data as $item) {

    $VarTheGUID = $item->id;

    $ImportThis = CheckGUIDImport($VarTheGUID,$TheUserID);

    //check original GUID and skip if we have it already
    if ($ImportThis == true) {


      $VarTheTitle = empty($item->caption->text) ? 'Untitled':$item->caption->text;

      $VarTheImageURL = $item->images->standard_resolution->url;
      $VarTheImageURL = $item->images->standard_resolution->url;
      $VarTheCreatedTime = $item->created_time;




              //title = Replace(title,"'","&#39;")
              // also convert double quotes!




      $VarTheTitle = removeEmoji($VarTheTitle);

      $ThisInstagramUsername = $item->user->username;
      if ($VarMode == 1 && $HashSearch >= "a") {
          //check to see if the hash exists if not then dont show it
          $pos = strpos($VarTheTitle, $VarHashFilterWHashSearch1);

          if ($pos !== false) {
            // hashtag found so proceed
            $VarProceed = 1;

          } else {
            $VarProceed = 2;
          }

      } else if ($VarMode == 2) {
        if ($InstagramUsername == $ThisInstagramUsername){
          //check to see if post is by user if so dont show it
          $VarProceed = 2;
        } else {
          $VarProceed = 1;

        }
      }


        if ($VarProceed == 1) {

          $VarTheTagsString = "";
          $VarTheTags = getHashtags($VarTheTitle);

          $VarTheDescription = $VarTheTitle;

            $periodpos = strpos($VarTheTitle, ". ");

            if ($periodpos >= 6) {
              // period found so split the title and description
              $VarTheDescription = substr($VarTheTitle, $periodpos+1);
              $VarTheTitle = substr($VarTheTitle, 0, $periodpos+1);

            } else {

                $exlaimpos = strpos($VarTheTitle, "! ");

                if ($exlaimpos >= 6) {
                  // exclamation found so split the title and description
                  $VarTheDescription = substr($VarTheTitle, $exlaimpos+1);
                  $VarTheTitle = substr($VarTheTitle, 0, $exlaimpos+1);

                }

            }

          $VarTheDescription = LinkifyTheAtMentions($VarTheDescription);


          // input stuff to database

          //format strings for field input

          $ThisContent = "<img src=" . $VarTheImageURL . "  class=\"instagram-pics\"><br />" . $VarTheDescription . "<br /><br />";

          $tempusername = "";
          if ($InstagramUsername <> $ThisInstagramUsername){
            $ThisContent = $ThisContent . "<span class=\"instagram-attribution\">posted by <a href=\"http://instagram.com/" . $ThisInstagramUsername . "\">@" . $ThisInstagramUsername . "</a></span><br /><br />";
            $tempusername = $ThisInstagramUsername;
          }

          $VarTheDatePosted = date("Y-m-d H:i:s",$VarTheCreatedTime);
          $VarThePostSlug = MakeTheSlug($VarTheTitle,$tempusername,$TheUserID);

          $ThisPostMode = 3;

          if ($VarMode==1) {
            $ThisPostMode = $VarInstagramPostMode;
          } else {
            $ThisPostMode = 3;
          }

          InsertEntry($VarTheTitle,$VarTheImageURL,$ThisContent,$VarTheGUID,$VarTheTags,$VarThePostSlug,$VarTheDatePosted,$ThisPostMode,$ThePageID,$TheUserID);


          if ($VarTheGUID >= "1" && $VarLoopCounter == 1 && $VarMode==1) {
            UpdateMinID($VarTheGUID,$TheUserID,$VarTheField);
          }

          if ($TheRealMinID >= 1 && $VarLoopCounter == 1 && $VarMode==2) {
            UpdateMinID($TheRealMinID,$TheUserID,$VarTheField);
          }

          $VarLoopCounter = $VarLoopCounter + 1;
        }
    }
  }

}

function removeEmoji($text) {

    $clean_text = "";

    // Match Emoticons
    $regexEmoticons = "/[\x{1F600}-\x{1F64F}]/u";
    $clean_text = preg_replace($regexEmoticons, "", $text);

    // Match Miscellaneous Symbols and Pictographs
    $regexSymbols = "/[\x{1F300}-\x{1F5FF}]/u";
    $clean_text = preg_replace($regexSymbols, "", $clean_text);

    // Match Transport And Map Symbols
    $regexTransport = "/[\x{1F680}-\x{1F6FF}]/u";
    $clean_text = preg_replace($regexTransport, "", $clean_text);

    return $clean_text;
}

function MakeTheSlug($replacestring,$username,$TheUserID) {


    $strPattern = "/[^A-Za-z 0-9]*/";

    $replacestring = preg_replace($strPattern, "", $replacestring);

    $replacestring = str_replace(" ","-",$replacestring);
    $replacestring = str_replace("----","-",$replacestring);
    $replacestring = str_replace("---","-",$replacestring);
    $replacestring = str_replace("--","-",$replacestring);
    $replacestring = str_replace("--","-",$replacestring);
    $replacestring = str_replace("--","-",$replacestring);
    $replacestring = str_replace("--","-",$replacestring);
    $replacestring = str_replace("--","-",$replacestring);
    $replacestring = strtolower($replacestring);

    $replacestring = trim($replacestring, "-");

    if ($username <> "") {
      $replacestring = $replacestring . "-" . $username;
    }

    // check to see if SLUG is unique - add a dash and timestamp to make it so if not

    $conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
    mysql_select_db($dbname);
    $DBSQL = "SELECT * FROM ENTRIES WHERE (SlugURL = '$replacestring') AND (UserID = $TheUserID) ";

      $TheResults = mysql_query($DBSQL);
      while ($TheFields = mysql_fetch_assoc($TheResults)) {
        $replacestring = $replacestring . "-" . strtotime("now");
      }


    mysql_close($conn);

    return $replacestring;
}

function LinkifyTheAtMentions($the_text) {
  $the_text = preg_replace('/(?<=^|\s)@([a-z0-9_]+)/i','<a href="http://instagram.com/$1">@$1</a>',$the_text);
  return $the_text;
}

function GetIGUserID($VarInstagram,$TheUserID) {

  $conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
  mysql_select_db($dbname);


    global $ThisUserToken;

    $userresult = fetchData("https://api.instagram.com/v1/users/search?q=" . $VarInstagram . "&access_token=" . $ThisUserToken);
    $userresult = json_decode($userresult);
    foreach ($userresult->data as $useritem) {

      //make sure its the proper user!
      if ($VarInstagram == $useritem->username){
        $ThisUsersIGID = $useritem->id;
      }
    }

    //save InstagramUSERID in database
    $IGUIDSQL="UPDATE USERS set InstagramUSERID=$ThisUsersIGID WHERE ID=$TheUserID";

    if (!mysql_query($IGUIDSQL,$conn))
      {
      die('Error: ' . mysql_error());
      }

    mysql_close($conn);

    return $ThisUsersIGID;
}

function UpdateTimestamp($VarTimestamp,$TheUserID) {

  $conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
  mysql_select_db($dbname);

    //save InstagramUSERID in database
    $IGUIDSQL="UPDATE USERS set InstagramLastUpdate=$VarTimestamp WHERE ID=$TheUserID";

    if (!mysql_query($IGUIDSQL,$conn))
      {
      die('Error: ' . mysql_error());
      }

    mysql_close($conn);

}

function InsertEntry($VarTheTitle,$VarTheImageURL,$ThisContent,$VarTheGUID,$VarTheTags,$VarThePostSlug,$VarTheDatePosted,$ThisPostMode,$ThePageID,$TheUserID) {

    // legacy time field fixup
    $spacepos = strpos($VarTheDatePosted, " ");
    $VarTheTimePosted = substr($VarTheDatePosted, $spacepos);

    $VarTheTitle = str_replace(chr(39),"&#39;",$VarTheTitle);
    $ThisContent = str_replace(chr(39),"&#39;",$ThisContent);

  $conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
  mysql_select_db($dbname);

    //save entry in database

    $ADDSQL = "INSERT INTO ENTRIES (UserID,Page,Title,Featured,Position,Content,Tags,ImageName,SlugURL,DatePosted,TimePosted,OriginalGUID,SourceID) VALUES(" . $TheUserID . "," . $ThePageID . ",'" . $VarTheTitle . "'," . $ThisPostMode . ",1000001,'" . $ThisContent . "','" . $VarTheTags . "','" . $VarTheImageURL . "','" . $VarThePostSlug . "','" . $VarTheDatePosted . "','" . $VarTheTimePosted . "','" . $VarTheGUID . "',18)";

    if (!mysql_query($ADDSQL,$conn))
      {
      die('Error: ' . mysql_error() . '<hr>' . $ADDSQL);
      }

    mysql_close($conn);

}


function UpdateMinID($VarTheMinID,$TheUserID,$VarTheField) {

  $conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
  mysql_select_db($dbname);

    //save InstagramUSERID in database
    $IGUIDSQL="UPDATE USERS set $VarTheField='$VarTheMinID' WHERE ID=$TheUserID";

    if (!mysql_query($IGUIDSQL,$conn))
      {
      die('Error: ' . mysql_error());
      }

    mysql_close($conn);

}

function CheckGUIDImport($VarTheGUID,$TheUserID) {

  $ImportThis = true;

  $conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
  mysql_select_db($dbname);
  $DBSQL = "SELECT * FROM ENTRIES WHERE (OriginalGUID = '$VarTheGUID') AND (UserID = $TheUserID) ";

    $TheResults = mysql_query($DBSQL);
    while ($TheFields = mysql_fetch_assoc($TheResults)) {
      $ImportThis = false;
    }

  return $ImportThis;

  mysql_close($conn);

}

?>
