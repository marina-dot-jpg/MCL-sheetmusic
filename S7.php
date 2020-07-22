<style type="text/css">
<!--
body,table,tr,td {
	font-family:Verdana, Tahoma, Arial, Trebuchet MS, Sans-Serif, Georgia, Courier, Times New Roman, Serif;
	font-size:14px;
}
-->
</style>
<?php
/*
Argon Search
Main Search script
v 1.0
(C) 2006 Helithia Productions
*/

include "query_cleaner.php";
//Define the SQL database login information
include "info.php";

$resultcount = 0;
//attempt to connect to database server
mysql_connect($dbhost,$dbuser,$dbpass) 
	or
die("Unable to connect to database server. If this problem persists, please contact an administrator<br>".mysql_error());


//select our database
mysql_select_db('mainlib_mcl_songindex');

$ip = $_SERVER['REMOTE_ADDR'];
$para = substr($ip,0,3);
if($para == '209')
{
	$internal = 1;
}
else
{
	$internal = 0;
}
$starttime = time();
$parsedsearcher = parseAll($_POST['Search']);
$parsedsearcher2 = parseAll2($_POST['Search']);
$statsquery = 'INSERT INTO stats VALUES (30,'.$starttime.",\"".$parsedsearcher."\",'".$ip."',".$internal.',0)';
mysql_query($statsquery) or die(mysql_error());


if ($_POST['type'] == 'all') {

    ///BEGIN Exact Phrase\\\
    //query the database for an exact match to the search term	
	$isQueryBad = checkForBad($parsedsearcher);
	if ($isQueryBad === 0)
	{
		
		$query="SELECT TITLE,REL_TITLE,FOUND_IN,MYKEY FROM mcl_sindex 
					where title like \"%".$parsedsearcher2."%\" or REL_TITLE like 
					\"%".$parsedsearcher2."%\" or title like \"%".$parsedsearcher."%\" or REL_TITLE like 
					\"%".$parsedsearcher."%\" order by 
					title";
		$result = mysql_query($query);
		$resultcount=0;
    	//output any results
		echo '<table width="100%"  style = "border: 1px solid #000000" cellspacing="0" cellpadding="3"><tr><td width="34%">Title</td><td width="24%">Subtitle/Source/Additional Information</td><td width="40%">Found In</td></tr>';
		
		while ($row = mysql_fetch_array($result)) 
		{
			if (isset($row["REL_TITLE"]) === FALSE)
			{
				$row["REL_TITLE"] = "&nbsp;";
			}
			echo "<tr bgcolor='#99CCFF'><td style = 'border: 1px solid #FFFFFF'>".$row["TITLE"]."</td><td style = 'border: 1px solid #FFFFFF'>".$row["REL_TITLE"]."</td><td style = 'border: 1px solid #FFFFFF'>".$row["FOUND_IN"]."</td></tr>";
			$resultcount=$resultcount+1;
		}
    	//clear result variable to reduce memory use on the server
    	mysql_free_result($result);
    	///End Exact Phrase\\\
	}
} 


elseif ($_POST['type'] == 'any') {

    /*NOTE: To allow the most likely results to be placed on top, an exact phrase search is done before the any-of-the-words spider is called on */

    ///BEGIN Exact Phrase\\\
    //query the database for an exact match to the search term	
	$parsedsearcher = parseAll($_POST['Search']);
	$isQueryBad = checkForBad($parsedsearcher);
	$gotResult = 0;
	if ($isQueryBad === 0)
	{
		
		$query="SELECT TITLE,REL_TITLE,FOUND_IN FROM mcl_sindex where title like \"%".$parsedsearcher."%\" or REL_TITLE like \"%".$parsedsearcher."%\" order by title";		$result = mysql_query($query);
		$resultcount=0;

		//output any results
		$output = '<table width="100%"  style = "border: 1px solid #000000" cellspacing="0" cellpadding="3"><tr><td width="34%">Title</td><td width="24%">Subtitle/Source/Additional Information</td><td width="40%">Found In</td></tr>';
		
		while ($row = mysql_fetch_array($result))
		{
			if (isset($row["REL_TITLE"]) === FALSE)
			{
				$row["REL_TITLE"] = "&nbsp;";
			}
			$output = $output."<tr bgcolor='#99CCFF'><td style = 'border: 1px solid #FFFFFF'>".$row["TITLE"]."</td><td style = 'border: 1px solid #FFFFFF'>".$row["REL_TITLE"]."</td><td style = 'border: 1px solid #FFFFFF'>".$row["FOUND_IN"]."</td></tr>";
			$resultcount=$resultcount+1;
		}
    	//clear result variable to reduce memory use on the server
    	mysql_free_result($result);
		if ($resultcount != 0)
		{
			echo($output);
			$gotResult = 1;
		}
    	///End Exact Phrase\\\
	}

    ///Begin Any Of The Words Search\\\
    //form any-of-the-words query
	$term=$_POST['Search'];
    //generates an upercase-only version of the search term (necessary since the PHP filter used later on is case sensitive)
	$uterm=strtoupper($term);
	//breaks the original search term into its individual words
    $terms=cleanQuery($uterm);
	if ($terms[0] >= 0 and $isQueryBad === 0)
	{
		$substring=0;
		//finds the number of words stored in the $terms array 
		$count=count($terms);
		//defines the base query
		$query2="SELECT TITLE,REL_TITLE,FOUND_IN FROM mcl_sindex where";
		while ($substring < $count){
			if ($substring >= 1) {
				//adds data into query for the latter words of the search term
				$query2=$query2." or title like \"% ".$terms[$substring]." %\" or title like \"% ".$terms[$substring]."\" or title like \"".$terms[$substring]." %\" or REL_TITLE like \"(".$terms[$substring]." %\" or REL_TITLE like \"% ".$terms[$substring].")\" or REL_TITLE like \"% ".$terms[$substring]." %\"";
				$substring=$substring+1;
			} else {
				//adds data into the query for the first word of the search term
				$query2=$query2." title like \"% ".$terms[$substring]." %\" or title like \"% ".$terms[$substring]."\" or title like \"".$terms[$substring]." %\" or REL_TITLE like \"(".$terms[$substring]." %\" or REL_TITLE like \"% ".$terms[$substring].")\" or REL_TITLE like \"% ".$terms[$substring]." %\"";
				$substring=$substring+1;
			}
		}
		//adds on the order by title directive to alphabeticly organize results by title
		$query2=$query2."GROUP BY title,REL_TITLE,FOUND_IN order by TITLE,REL_TITLE";
		$result2 = mysql_query($query2);
		//organize results
		$k = 0;
		$anyrcount = 0;
		while ($row2 = mysql_fetch_array($result2)) 
		{	
			$i = 0;
			$i2 = 0;	
			$relevance = 0;
			//checks to be sure that the result was not picked up by the first spidering process
			if (strpos(" ".$row2["TITLE"],$uterm) == 0 and strpos(" ".$row2["REL_TITLE"],$uterm) == 0)
			{
				foreach ($terms as $term1)
				{
						$g = 0;
						for ($c = 0; $c + strlen($term1) <= strlen($row2["TITLE"]); $c = $c + 1)
						{
						
							$i = strpos(" ".$row2["TITLE"],$term1,$g);
							if ($i != FALSE)
							{
								$relevance = $relevance + 1;
								$g = $i + 1;
								if ($g + strlen($term1) > strlen($row2["TITLE"]))
								{
									break;
								}
							}
							else
							{
								break;
							}
						
						}
						$g = 0;
						for ($d = 0; $d + strlen($term1) <= strlen($row2["REL_TITLE"]); $d = $d + 1)
						{
						
							$i = strpos(" ".$row2["REL_TITLE"],$term1,$g);
							if ($i != FALSE)
							{
								$relevance = $relevance + 1;
								$g = $i + 1;
								if ($g + strlen($term1) > strlen($row2["REL_TITLE"]))
								{
									break;
								}
							}
							else
							{
								break;
							}
							
						}
				}
				$relevant[$k]["title"] = $row2["TITLE"];
				$relevant[$k]["subtitle"] = $row2["REL_TITLE"];
				$relevant[$k]["found_in"] = $row2["FOUND_IN"];
				$relevant[$k]["rel"] = $relevance;
				if ($relevant[$k]["subtitle"] == "")
				{
					$relevant[$k]["subtitle"] = "&nbsp;";
				}
				$k = $k + 1;
				$resultcount=$resultcount+1;
				$anyrcount = $anyrcount + 1;
			}	 
		}
		mysql_free_result($result2); //clear result variable to reduce memory use on the server

		function cmp($a, $b)
		{
			if ($a["rel"] == $b["rel"]) 
			{
       			return 0;
   			}
   			else
			{
				return ($a["rel"] > $b["rel"]) ? -1 : 1;
			}
		}
		if ($anyrcount > 0)
		{
			usort($relevant,"cmp");
		
			if ($gotResult === 1)
			{
				$output = "";
			}
			else
			{
				$output = '<table width="100%"  style = "border: 1px solid #000000" cellspacing="0" cellpadding="3"><tr><td width="34%">Title</td><td width="24%">Subtitle/Source/Additional Information</td><td width="40%">Found In</td></tr>';
			}
			$f = 0;
			while ($f < $k)
			{
				$output = $output."<tr bgcolor='#F9FCC6'><td style = 'border: 1px solid #FFFFFF'>".$relevant[$f]["title"]."</td><td style = 'border: 1px solid #FFFFFF'>".$relevant[$f]["subtitle"]."</td><td style = 'border: 1px solid #FFFFFF'>".$relevant[$f]["found_in"]."</td></tr>";
				$f = $f + 1;
			}
   			if ($f != 0)
			{
				echo($output);
			}
		}

	///End Any Of The Words Search\\\
	}
}
	
//output error if no result is found
if ($resultcount==0) {

	echo "<table cellspacing = \"0\" bgcolor = '#F0F5FA' align = 'center'><tr><td style=\"border:1px solid #C2CFDF\" bgcolor = '#F0F5FA' align = 'center'>";
	if ($isQueryBad === 1)
	{
		include "filteredwords.php";
		if ($parsedsearcher === " ")
		{
			echo " Error: Invalid Search Term (Space at Beginning). <br /><a href = 'search.php'> Click here to return to the search form </a>";
		}
		else if ($parsedsearcher === "")
		{
			echo " Error: No search term entered. <br /><a href = 'search.php'> Click here to return to the search form</a>";
		}
		else if (strlen($parsedsearcher) === 1)
		{
			echo " Error: One character search term. Please enter a longer search term. <br /><a href = 'search.php'> Click here to return to the search form</a>";
		}
		foreach ($filteredwords as $w)
		{
			if (strtoupper($parsedsearcher) === $w)
			{
				echo " Error: Illegal search word: ".$w." without other words in query. Please add more words to your query. <br /><a href = 'search.php'> Click here to return to the search form </a>";
				break;
			}
			
		}
		
	}
	else //no results
	{
		echo "Sorry, no results were found that matched your query of ".$_POST['Search'].".<br />";
		$s = 0;
		//generate "helpful" error based off common problems with Any of the Words
		if ($_POST['type'] == 'any')
		{
			include "filteredwords.php";
			//breaks query into terms for analysis of failure
			$terms1 = explode(" ",strtoupper($parsedsearcher));
			foreach ($filteredwords as $w)
			{
				foreach ($terms1 as $tm) //walks query array
				{

					if ($tm === $w) //if a word matches one known to be filtered
					{
						if ($s === 0) //if this is the beginning of the error addenda
						{
							echo "Note: The following words were automaticly removed from the second phase of the Any of the words Search: ".$w;
							$s = 1;
							
						}
						else //if it isn't the beginning of the error addenda
						{
							echo ", ".$w;
						}
					}
				}
			}
		}
		if ($s == 1)
		{
			echo "<br />"; //adds extra line break if a "helpful" error was thrown
		}
		echo "<a href = 'search.php'>Click here to return to the search form</a>"; //Prints out link back to the main form
	}
	echo "</tr></td></table>"; //closes out error box
}
else 
{
	echo "</table>"; //ends table if results were found
}
$endtime = time();
$runtime = $endtime - $starttime;

$statsquery2 = "UPDATE stats SET runtime = ".$runtime." WHERE time = ".$starttime." and query = \"".$parsedsearcher."\"";
mysql_query($statsquery2);
$statsquery3 = "UPDATE stats SET resultcount = ".$resultcount." WHERE time = ".$starttime." and query = \"".$parsedsearcher."\"";
mysql_query($statsquery3);
echo ("Query took ".($runtime + 1)." second(s) to run. Query received on ".strftime("%A, %B %d at %I:%M:%S %p %Z",$starttime)." from an ");
if ($internal == 0)
{
	echo "external referrer. ";
}
else
{
	echo "internal referrer. ";
}
echo($resultcount." result(s) processed by the server.");
 
?>

