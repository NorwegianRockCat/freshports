<?
	# $Id: port-watch.php,v 1.1.2.24 2002-12-13 20:35:26 dan Exp $
	#
	# Copyright (c) 1998-2001 DVL Software Limited

	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/common.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/freshports.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/databaselogin.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/getvalues.php');
	require_once($_SERVER['DOCUMENT_ROOT'] . '/include/watch-lists.php');

	require_once($_SERVER['DOCUMENT_ROOT'] . '/../classes/categories.php');

	$submit	= $_POST['submit'];
	$visitor	= $_COOKIE['visitor'];

// if we don't know who they are, we'll make sure they login first
if (!$visitor) {
	header('Location: login.php?origin=' . $_SERVER['PHP_SELF']);  /* Redirect browser to PHP web site */
	exit;  /* Make sure that code below does not get executed when we redirect. */
}

$category = $_REQUEST['category'];


// find out the watch id for this user's main watch list
$sql_get_watch_ID = "select watch_list.id ".
                    "  from watch_list ".
                    " where watch_list.user_id = $User->id ".
                    "   and watch_list.name    = 'main'";

if ($submit) {
   $result = pg_exec($db, $sql_get_watch_ID);
   $numrows = pg_numrows($result);
   if($numrows) {
      $myrow = pg_fetch_array ($result, 0);
      $WatchID = $myrow["id"];
   } else {
      // create their main list for them
      $sql_create = "insert into watch_list (name, owner_user_id) values ('main', $User->id)";
      $result = pg_exec($db, $sql_create);

      // refetch our watch id
      $result = pg_exec ($db, $sql_get_watch_ID);

      $myrow = pg_fetch_array ($result, 0);
      $WatchID = $myrow["id"];

   }

// delete existing watch_category entries for this watch
	$sql = "delete from watch_list_element where exists (
	        select element.id
	          from ports, element
	         where watch_list_element.watch_list_id = $WatchID 
	           and watch_list_element.element_id    = element.id 
	           and ports.element_id                 = element.id 
	           and ports.category_id                = $CategoryID)";


	$result = pg_exec($db, $sql);
     
    
   $ports = $_POST["ports"];
   if ($ports) {
      // make sure we are pointing at the start of the array.
      reset($ports);
      while (list($key, $value) = each($ports)) {
         $sql = "insert into watch_list_element (watch_list_id, element_id) ".
                "values ($WatchID, $value)";
   

         $result = pg_exec ($db, $sql);
         ${"port_".$value} = 1;
      }
   }
      
   header("Location: watch-categories.php");  /* Redirect browser to PHP web site */
   exit;  /* Make sure that code below does not get executed when we redirect. */
      
} else {
         
   if ($User->id != '') {
         
	   // read the users current watch information from the database
	
	   $sql = "select watch_list_element.element_id " .
	          "  from watch_list_element, watch_list, ports " .
	          " where watch_list_element.watch_list_id = watch_list.id " . 
	          "   and watch_list.user_id               = $User->id " .
			  "   and watch_list_element.element_id    = ports.element_id";
	      
		$result = pg_exec($db, $sql);
		$numrows = pg_numrows($result);      
	   // read each value and set the variable accordingly
		for ($i = 0; $i < $numrows; $i++) {
			$myrow = pg_fetch_array($result, $i);
			// we use these to see if a particular port is selected
			${"port_".$myrow["element_id"]} = 1;
		}
   }

   freshports_Start($category,
               "freshports - new ports, applications",
               "FreeBSD, index, applications, ports");
}

?>

<table width="100%" border="0">
<tr><td valign="top" width="100%">
<table width="100%" border="0">
  <tr>
	<? echo freshports_PageBannerText("Watch List - " . $category) ?>
  </tr>

<tr><td valign="top">
<UL>
<LI>This page shows you the ports in a category (<em><?echo $category->{name} ?></em>)
that are on your watch list.</LI>
<LI>The entries with a tick beside them are your watch list.</LI>
<LI>When one of the ports in your watch list changes, you will be notified by email if
you have selected a notification frequency within your <a href="customize.php">personal preferences</a>.
</LI>
<LI>[D] indicates a port which has been removed from the tree.</LI>
</UL>
<?php

$DESC_URL = "ftp://ftp.freebsd.org/pub/FreeBSD/branches/-current/ports";

$sql = "
  select element.id, 
         element.name as port, 
         element.status, 
         categories.name as category
    from ports, element, categories
   WHERE categories.name   = '$category'
     and ports.element_id  = element.id 
     and ports.category_id = categories.id 
order by element.name";

if ($Debug) echo "<pre>$sql</pre>\n";

$result = pg_exec($db, $sql);

$HTML .= '<tr><td valign="top" ALIGN="center">' . "\n";

$numrows = pg_numrows($result);
if ($numrows) {
	
	$HTML .= '<table border="0"><tr><td>';
	$HTML .= '<table border="0"><tr><td>';

	$HTML .= '<form action="' . $_SERVER["PHP_SELF"] . "?category=$CategoryID". '" method="POST">';

   $HTML .= "\n" . '<TABLE BORDER="1" CELLSPACING="0" CELLPADDING="5" BORDERCOLOR="#a2a2a2" BORDERCOLORDARK="#a2a2a2" BORDERCOLORLIGHT="#a2a2a2">' . "\n";

   // get the list of ports

	$NumPorts = 0;
	for ($i = 0; $i < $numrows; $i++) {
		$myrow = pg_fetch_array($result, $i);
		$NumPorts++;
		$rows[$NumPorts-1]=$myrow;
	}

   // save the number of categories for when we submit
   $HTML .= '<input type="hidden" name="NumPorts" value="' . $NumPorts . '">';

   $RowCount = ceil($NumPorts / (double) 4);
   $Row = 0;
   for ($i = 0; $i < $NumPorts; $i++) {
      $Row++;

      if ($Row > $RowCount) {
         $HTML .= "</td>\n";
         $Row = 1;
      }

      if ($Row == 1) {
         $HTML .= '<td valign="top">';
      }

      $HTML .= '<input type="checkbox" name="ports[]" value="'. $rows[$i]["id"] .'"';

      if (${"port_".$rows[$i]["id"]}) {
         $HTML .= " checked ";
      }

      $HTML .= '>';

      $HTML .= ' <a href="/' . $rows[$i]["category"] . '/' . $rows[$i]["port"] . '/">' . $rows[$i]["port"] . '</a>';

      if ($rows[$i]["status"] == 'D') {
         $HTML .= " [D]";
      }

      $HTML .= "<br>\n";
   }

   if ($Row != 1) {
      $HTML .= "</td></tr>\n";
   }

   $HTML .= "</table>\n";
   
   echo $HTML;
?>
<TR><TD>&nbsp;</TD></TR>
<tr><td ALIGN="center">

<input TYPE="submit" VALUE="update watch list" name="submit">
<input TYPE="reset"  VALUE="reset form">
</td></tr>
</form>
</table>

<td valign="top">
<table border="0"><tr><td>Select...</td></tr><tr><td>
   
<?php
	$Extra = '<input type="hidden" name="category" value="' . $category . '">';
	echo freshports_WatchListDDLBForm($db, $User->id, $WatchListID, $Extra);
?>
  </td></tr></table>
  </td></tr></table>

<?php
} else {
	echo '<tr><td ALIGN="center">' . "\n";
   echo "No ports found.  perhaps this is an invalid category id.";
	echo "</td></tr>\n";
}


?>
</table>

</td>
  <TD VALIGN="top" WIDTH="*" ALIGN="center">
    <? require_once($_SERVER['DOCUMENT_ROOT'] . '/include/side-bars.php') ?>
 </td>
</tr>
</table>

<TABLE WIDTH="<? echo $TableWidth; ?>" BORDER="0" ALIGN="center">
<TR><TD>
<? require_once($_SERVER['DOCUMENT_ROOT'] . '/include/footer.php') ?>
</TD></TR>
</TABLE>


</body>
</html>
