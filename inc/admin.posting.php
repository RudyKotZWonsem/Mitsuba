<?php
function deletePostMod($conn, $board, $postno, $onlyimgdel = 0)
{
	
	if (is_numeric($postno))
	{
		$board = $conn->real_escape_string($board);
		if (!isBoard($conn, $board))
		{
			return -16;
		}
		$result = $conn->query("SELECT * FROM posts_".$board." WHERE id=".$postno);
		if ($result->num_rows == 1)
		{
			$postdata = $result->fetch_assoc();
		
			if ($onlyimgdel == 1)
			{
				if ((!empty($postdata['filename'])) && ($postdata['filename'] != "deleted"))
				{
						
					$filename = $postdata['filename'];
					if (substr($filename, 0, 8) == "spoiler:")
					{
						$filename = substr($filename, 8);
					}
					if ((substr($filename, 0, 6) != "embed:") && ($filename != "deleted"))
					{
						unlink("./".$board."/src/".$filename);
						unlink("./".$board."/src/thumb/".$filename);
					}
					$conn->query("UPDATE posts_".$board." SET filename='deleted' WHERE id=".$postno.";");
					if ($postdata['resto'] != 0)
					{
						generateView($conn, $board, $postdata['resto']);
						generateView($conn, $board);
					} else {
						generateView($conn, $board, $postno);
						generateView($conn, $board);
					}
					return 1; //done-image
				} else {
					return -3;
				}
			} else {
				if ($postdata['resto'] == 0) //we'll have to delete whole thread
				{
					$files = $conn->query("SELECT * FROM posts_".$board." WHERE filename != '' AND resto=".$postdata['id']);
					while ($file = $files->fetch_assoc())
					{
						$filename = $file['filename'];
						if (substr($filename, 0, 8) == "spoiler:")
						{
							$filename = substr($filename, 8);
						}
						if ((substr($filename, 0, 6) != "embed:") && ($filename != "deleted"))
						{
							unlink("./".$board."/src/".$filename);
							unlink("./".$board."/src/thumb/".$filename);
						}
					}
					if ((!empty($postdata['filename'])) && ($postdata['filename'] != "deleted"))
					{
						$filename = $postdata['filename'];
						if (substr($filename, 0, 8) == "spoiler:")
						{
							$filename = substr($filename, 8);
						}
						if ((substr($filename, 0, 6) != "embed:") && ($filename != "deleted"))
						{
							unlink("./".$board."/src/".$filename);
							unlink("./".$board."/src/thumb/".$filename);
						}
					}
					$conn->query("DELETE FROM posts_".$board." WHERE resto=".$postno.";");
					$conn->query("DELETE FROM posts_".$board." WHERE id=".$postno.";");
					unlink("./".$board."/res/".$postno.".html");
					//generateView($conn, $board, $postno);
					generateView($conn, $board);
					return 2; //done post
				} else {
					if ((!empty($postdata['filename'])) && ($postdata['filename'] != "deleted"))
					{
						
						$filename = $postdata['filename'];
						if (substr($filename, 0, 8) == "spoiler:")
						{
							$filename = substr($filename, 8);
						}
						if ((substr($filename, 0, 6) != "embed:") && ($filename != "deleted"))
						{
							unlink("./".$board."/src/".$filename);
							unlink("./".$board."/src/thumb/".$filename);
						}
					}
					$conn->query("DELETE FROM posts_".$board." WHERE id=".$postno.";");
					generateView($conn, $board, $postdata['resto']);
					generateView($conn, $board);
					return 2;
				}
			}
			
		} else {
			return -2;
		}
	} else {
		return -2;
	}
}

function generatePost($conn, $board, $id)
{
	if ((empty($id)) || (!is_numeric($id)))
	{
		return -15;
	}
	if ((empty($id)) || (!isBoard($conn, $board)))
	{
		return -16;
	}
	$result = $conn->query("SELECT * FROM posts_".$board." WHERE id=".$id);
	if ($result->num_rows == 1)
	{
		$post = $result->fetch_assoc();
		if ($post['resto'] == 0)
		{
			generateView($conn, $board, $post['id']);
		} else {
			generateView($conn, $board, $post['resto']);
		}
		generateView($conn, $board);
	}
}

function addPostMod($conn, $board, $name, $email, $subject, $comment, $password, $filename, $orig_filename, $resto = 0, $md5 = "", $spoiler = 0, $embed = 0, $capcode = 0, $raw = 0, $sticky = 0, $locked = 0, $nolimit = 0)
{
	if (!isBoard($conn, $board))
	{
		return -16;
	}
	if (!is_numeric($resto))
	{
		$resto = 0;
	}
	if ((!is_numeric($raw)) || ($_SESSION['type'] == 0))
	{
		$raw = 0;
	}
	if ((!is_numeric($capcode)) || ($_SESSION['type'] == 0))
	{
		$capcode = 0;
	}
	if ((!is_numeric($sticky)) || ($_SESSION['type'] == 0))
	{
		$sticky = 0;
	}
	if ((!is_numeric($locked)) || ($_SESSION['type'] == 0))
	{
		$locked = 0;
	}
	
	if ($resto != 0)
	{
		$sticky = 0;
		$locked = 0;
	}
	
	if (($resto == 0) && (empty($filename)))
	{
		echo "<center><h1>Error: No file selected.</h1><br /><a href='./".$board."'>RETURN</a></center>";
		return;
	}
	
	if ((empty($filename)) && (empty($comment)))
	{
		echo "<center><h1>Error: No file selected.</h1><br /><a href='./".$board."'>RETURN</a></center>";
		return;
	}

	$bdata = getBoardData($conn, $board);
	$fname2 = $filename;
	if ((!empty($filename)) && ($spoiler == 1) && ($bdata['spoilers'] == 1))
	{
		$filename = "spoiler:".$filename;
	}
	$embed_img = 0;
	if ((!empty($filename)) && ($embed == 1) && ($bdata['embeds'] == 1))
	{
		$fname2 = "embed";
		$embed_img = 1;
	}
	$thread = "";
	$tinfo = "";
	$replies = 0;
	if ($resto != 0)
	{
		$thread = $conn->query("SELECT * FROM posts_".$board." WHERE id=".$resto);
		
		if (($bdata['bumplimit'] > 0) && ($nolimit == 0))
		{
			$replies = $conn->query("SELECT * FROM posts_".$board." WHERE resto=".$resto);
			$replies = $replies->num_rows;
		}
		
		if ($thread->num_rows == 0)
		{
			echo "<center><h1>Error: Cannot reply to thread because thread does not exist.</h1><br /><a href='./".$board."'>RETURN</a></center>";
			return;
		}
		$tinfo = $thread->fetch_assoc();
		
	}
	$lastbumped = time();
	$trip = "";
	if (($bdata['noname'] == 1) && ($_SESSION['type']==0))
	{
		$name = "Anonymous";
	} else {
		$name = processString($conn, $name, 1);
		if (isset($name['trip']))
		{
			$trip = $name['trip'];
			$name = $name['name'];
		}
	}
	$poster_id = "";
	if ($bdata['ids'] == 1)
	{
		if ($resto != 0)
		{
			$poster_id = mkid($_SERVER['REMOTE_ADDR'], $resto, $board);
		}
		
	}
	$md5 = $conn->real_escape_string($md5);
	$isize = "";
	$fsize = "";
	if ((!empty($fname2)) && ($fname2 != "embed"))
	{
		if (substr($filename, 0, 8) == "spoiler:")
		{
			$d = getimagesize("./".$board."/src/".substr($filename, 8));
			$isize = $d[0]."x".$d[1];
			$fsize = human_filesize(filesize("./".$board."/src/".substr($filename, 8)));
		} else {
			$d = getimagesize("./".$board."/src/".$filename);
			$isize = $d[0]."x".$d[1];
			$fsize = human_filesize(filesize("./".$board."/src/".$filename));
		}
	}
	$conn->query("INSERT INTO posts_".$board." (date, name, trip, poster_id, email, subject, comment, password, orig_filename, filename, resto, ip, lastbumped, filehash, filesize, imagesize, sticky, sage, locked, capcode, raw)".
	"VALUES (".time().", '".$name."', '".$trip."', '".$conn->real_escape_string($poster_id)."', '".processString($conn, $email)."', '".processString($conn, $subject)."', '".preprocessComment($conn, $comment)."', '".md5($password)."', '".processString($conn, $orig_filename)."', '".$filename."', ".$resto.", '".$_SERVER['REMOTE_ADDR']."', ".$lastbumped.", '".$md5."', '".$fsize."', '".$isize."', ".$sticky.", 0, ".$locked.", ".$capcode.", ".$raw.")");
	$id = mysqli_insert_id($conn);
	$poster_id = "";
	if ($bdata['ids'] == 1)
	{
		if ($resto == 0)
		{
			$poster_id = mkid($_SERVER['REMOTE_ADDR'], $id, $board);
		}
		
	}
	if ($poster_id != "")
	{
		$conn->query("UPDATE posts_".$board." SET poster_id='".$conn->real_escape_string($poster_id)."' WHERE id=".$id);
	}
	if ($resto != 0)
	{
		if (($email == "sage") || ($email == "nokosage") || ($email == "nonokosage") || ($tinfo['sage'] == 1) || ($replies > $bdata['bumplimit']))
		{
		
		} else {
			$conn->query("UPDATE posts_".$board." SET lastbumped=".time()." WHERE id=".$resto);
		}
	
	
	}
	if (($email == "nonoko") || ($email == "nonokosage"))
	{
		echo '<meta http-equiv="refresh" content="2;URL='."'?/board&b=".$board."'".'">';
		
	} else {
		if ($resto != 0)
		{
			echo '<meta http-equiv="refresh" content="2;URL='."'?/board&b=".$board."&t=".$resto."#p".$id."".'">';
		} else {
			echo '<meta http-equiv="refresh" content="2;URL='."'?/board&b=".$board."&t=".$id."'".'">';
			
		}
	}
	pruneOld($conn, $board);
	if ($resto == 0)
	{
		generateView($conn, $board, $id);
	} else {
		generateView($conn, $board, $resto);
	}
	generateView($conn, $board);
}
?>