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
$temporaryLocation = '/tmp/punbbarchiver-work';

$ftpConnection = null;

error_reporting(E_ALL);

function clearDirectoryRecursively($dir) {
  $dirhandle = dir($dir);
  while ($entry = $dirhandle->read()) {
    if (!in_array($entry, array('.', '..'))) {
      if (is_dir("$dir/$entry")) {
        clearDirectoryRecursively("$dir/$entry");
	removeDirectory("$dir/$entry");
      } else {
        removeFile("$dir/$entry");
      }
    }
  }
  return true;
}

function removeFile($fileName) {
  global $ftpConnection;
  ftp_delete($ftpConnection, "/httpdocs/tech/punbbarchiver/$fileName")
    or die("no file $fileName deleted");
}

function removeDirectory($directoryName) {
  global $ftpConnection;
  ftp_rmdir($ftpConnection, "/httpdocs/tech/punbbarchiver/$directoryName")
    or die("no dir $directoryName deleted");
}

header('Content-Type: text/plain; charset=' . $directoryEncoding);
$ftpConnection = ftp_connect('example') or die('no ftp connection');
ftp_login($ftpConnection, 'exampleuser', 'examplepassword') or die('no ftp login');

clearDirectoryRecursively($temporaryLocation);

ftp_close($ftpConnection);

print('Directory emptied.');
