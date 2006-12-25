<?php
/*
 * PunBBArchiver
 * Copyright 2006, Sander Dijkhuis <sander.dijkhuis@gmail.com>.
 *
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice, 
 *     this list of conditions and the following disclaimer.
 *  2. Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *  3. The name of the author may not be used to endorse or promote products 
 *     derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF 
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
 * EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, 
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR 
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF 
 * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
$VERSION = '20061208';
// WARNING!!! Code is not ready for use yet. It will be properly modified and
// documented.

define('STORAGE_METHOD_FTP', '1');
define('STORAGE_METHOD_FS', '2');
$storageMethod = STORAGE_METHOD_FS;

$temporaryLocation = '/tmp/punbbarchiver-work';
$tablePrefix = 'punbb_';
$topDirectoryName = 'forum.example.com';
$viewTopicAddress = 'http://forum.example.com/viewtopic.php?id=';
$viewPostAddress = 'http://forum.example.com/viewtopic.php?pid=';
$directoryEncoding = 'ASCII';
$sourceEncoding = 'UTF-8';
$forumTitle = 'Example forum';
$forumLocation = 'http://forum.example.com/';

$ftpConnection = null;
$temporaryFileHandle = null;
$temporaryFileName = 'tmpfile.txt';

error_reporting(E_ALL);

require 'connect.php';

function generateValidFileName($stringToUse) {
  global $directoryEncoding, $sourceEncoding;
  $generatedFileName = mb_convert_encoding($stringToUse, $directoryEncoding,
                                           $sourceEncoding);
  $replace = array('?', '/', '\\', ':', '*', '<', '>', '|', '"', '.');
  $generatedFileName = str_replace($replace, '_', $generatedFileName);
  return $generatedFileName;
}

function createDirectory($directoryName) {
  global $ftpConnection, $storageMethod;
  if ($storageMethod == STORAGE_METHOD_FTP) {
    ftp_mkdir($ftpConnection, "/httpdocs/tech/punbbarchiver/$directoryName")
      or die("no dir $directoryName");
  } elseif($storageMethod == STORAGE_METHOD_FS) {
    mkdir($directoryName) or die("no dir $directoryName");
  } else
    trigger_error('Incorrect storage method provided.', E_USER_ERROR);
}

function writeFile($fileName, $fileContent) {
  global $ftpConnection;
  global $temporaryFileHandle;
  global $temporaryFileName;
  global $storageMethod;
  if ($storageMethod == STORAGE_METHOD_FTP) {
    ftruncate($temporaryFileHandle, 0) or die("no truncate");
    rewind($temporaryFileHandle) or die("no rewind");
    fwrite($temporaryFileHandle, $fileContent);
    ftp_put($ftpConnection,
            "/httpdocs/tech/punbbarchiver/$fileName",
            $temporaryFileName,
            FTP_BINARY)
      or die("no file $fileName\n$temporaryFileName");
  } elseif ($storageMethod == STORAGE_METHOD_FS) {
    $file = fopen($fileName, 'w');
    fwrite($file, $fileContent);
    fclose($file);
  } else
    trigger_error('Incorrect storage method provided.', E_USER_ERROR);
}

function createArchive() {
  global $temporaryLocation, $tablePrefix, $readmeFileName, $directoryEncoding;
  global $viewTopicAddress;
  global $viewPostAddress;
  global $ftpConnection;
  global $temporaryFileHandle;
  global $temporaryFileName;
  global $storageMethod;
  global $VERSION;
  global $forumTitle;
  global $forumLocation;
  global $administratorName;
  global $administratorEmail;

  header('Content-Type: text/plain; charset=' . $directoryEncoding);

  if ($storageMethod == STORAGE_METHOD_FTP) {
    $ftpConnection = ftp_connect('example.com') or die('no ftp connection');
    ftp_login($ftpConnection, 'exampleuser', 'examplepassword') or die('no ftp login');
  }

  $temporaryFileHandle = fopen($temporaryFileName, 'w');

  $archivalInfoText = "Forum-Title: $forumTitle\n";
  $archivalInfoText .= "Forum-Location: <$forumLocation>\n";
  $archivalInfoText .= 'Generated-At: ' . date('r') . "\n";
  $archivalInfoText .= "Generated-By: PunBBArchiver $VERSION";
  $archivalInfoText .= " <http://code.google.com/p/punbbarchiver/>\n";
  $archivalInfoText = str_replace("\n", "\r\n", $archivalInfoText);
  writeFile("$temporaryLocation/INFO.txt", $archivalInfoText);

  $query = 'SELECT * FROM ' . $tablePrefix . 'categories';
  $categorySelectResult = mysql_query($query) or die(mysql_error());
  while ($category = mysql_fetch_object($categorySelectResult)) {

    $categoryDirectoryName = "$temporaryLocation/"
                             . generateValidFileName($category->cat_name);
    createDirectory($categoryDirectoryName);

    $query = 'SELECT * FROM ' . $tablePrefix . 'forums WHERE redirect_url'
             . " IS NULL AND cat_id = '$category->id'";
    $forumSelectResult = mysql_query($query) or die(mysql_error());
    while ($forum = mysql_fetch_object($forumSelectResult)) {

      $query = 'SELECT * FROM ' . $tablePrefix . 'forum_perms WHERE group_id ='
               . " 3 AND forum_id = '$forum->id' AND read_forum = 0";
      if (mysql_num_rows(mysql_query($query)) !== 0)
        continue;

      $forumDirectoryName = "$categoryDirectoryName/"
                            . generateValidFileName($forum->forum_name);
      createDirectory($forumDirectoryName);

      $topicTitlesInForum = array();

      $query = 'SELECT * FROM ' . $tablePrefix . 'topics WHERE forum_id = '
               . $forum->id;
      $topicSelectResult = mysql_query($query) or die(mysql_error());
      while ($topic = mysql_fetch_object($topicSelectResult)) {

        $topicInForum = generateValidFileName($topic->subject);

        if (in_array($topicInForum, $topicTitlesInForum)) {
	  for ($i = 2;
	       in_array("$topicInForum ($i)", $topicTitlesInForum);
	       $i++);
          $topicInForum = "$topicInForum ($i)";
        }

        $topicDirectoryName = "$forumDirectoryName/$topicInForum";
	createDirectory($topicDirectoryName);

	$topicFileContent = "Topic-ID: $topic->id\n";
	$topicFileContent .= "Location: <$viewTopicAddress$topic->id>\n";
	$topicFileContent .= "Subject: $topic->subject\n";
	$topicFileContent = str_replace("\n", "\r\n", $topicFileContent);
	writeFile("$topicDirectoryName/TOPIC.txt", $topicFileContent);

        $isFirstPost = true;
        $query = 'SELECT * FROM ' . $tablePrefix . 'posts WHERE topic_id = '
	         . $topic->id;
        $postSelectResult = mysql_query($query) or die(mysql_error());
        while ($post = mysql_fetch_object($postSelectResult)) {

          $postFileContent = 'Author: ' . $post->poster;
          if ($post->poster_id == 1)
            $postFileContent .= ' (guest)';
	  $postFileContent .= "\n";

          $postFileContent .= 'Subject: ';
          if (!$isFirstPost)
            $postFileContent .= 'Re: ';
          $postFileContent .= "$topic->subject\n";

          $postFileContent .= "Post-ID: $post->id\n";

          $postFileContent .= "Location:";
	  $postFileContent .= " <$viewPostAddress$post->id#p$post->id>\n";

          $postFileContent .= "Date: " . date('r', $post->posted) . "\n";

          if ($post->edited) {
	    $postFileContent .= "Edited-By: $post->edited_by\n";
            $postFileContent .= "Edited-Date: " . date('r', $post->edited)
	                        . "\n";
	  }

          $postFileContent .= "\n$post->message\n";

	  $postFileContent = str_replace("\n", "\r\n", $postFileContent);
	  writeFile("$topicDirectoryName/$post->id.txt", $postFileContent);

          $isFirstpost = false;
        }
      $topicTitlesInForum[] = $topicInForum;
      }
    }
  }

  if ($storageMethod == STORAGE_METHOD_FTP) {
    ftruncate($temporaryFile, 0);
    rewind($temporaryFile);
    fclose($temporaryFile);
    ftp_close($ftpConnection);
  }

  print('Archive created. Now run the script that creates a .tar.bz2 file.');
}

$microtime = microtime();
$split = explode(" ", $microtime);
$exact = $split[0];
$secs = date("U");
$bgtm = $exact + $secs;

createArchive();

$microend = microtime();
$split = explode(" ", $microend);
$exactend = $split[0];
$secsend = date("U");
$edtm = $exactend + $secsend;

$difference = $edtm - $bgtm;
$difference = round($difference,5);

print "\n$difference s";

