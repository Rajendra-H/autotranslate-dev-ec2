<?php
require './autoload.php';
use Aws\S3\S3Client;


$input_names = array('AJM','AJE','Reuters','CJM');
$keys = array('5be26e14212b2f12818459e4','5bea69e46d078aa9eae78c6c','5bea6a0f9eea97c93c3f11b8','5bf61cc2efe0aa94b74f9251');


for ($num=0; $num<count($keys); $num++){
    $date='';

    $filename="translate-$input_names[$num].xml";


    $file=file_get_contents("https://app.meltwater.com/gyda/outputs/$keys[$num]/rendering?apiKey=5657ab315f51bf057e88e651&type=html");


    $arr=explode("</channel>",$file);
    $message=explode("<item>\n<title><![CDATA[",$arr[0]);
    $converted=$message[0];

    for ($i= 1; $i<count($message); $i++){
        $arr=explode("</title>",$message[$i]);
        $tmp=explode("<description>",$arr[1]);
        $url=$tmp[0];
            $arr= explode("]]></title>",$message[$i]);
            $title=$arr[0];
            $last=$arr[1];
            $data=array('q'=>$title);
            $language=detect($data);
            // echo $language;

            $arr=explode("<description><![CDATA[",$arr[1]);
            $tmp=explode("]]></description>",$arr[1]);
            $str=$tmp[0];

            if ($language == 'en' ) {
                $converted = $converted."<item><title><![CDATA[".$title."]]></title>".$last;
            } else {
                $data=array('q'=>$title,'source'=>$language,'target'=>'en','format'=>'text');
                $result=translate($data);
                $converted=$converted."<item><title><![CDATA[".$result." (-".$title."-)"."]]></title>";


                if (count($arr) == 1) {
                    $converted=$converted.$last;
                }
                else {
                    $data=array('q'=>$str,'source'=>$language,'target'=>'en','format'=>'text');

                    $result=translate($data);

                    $converted=$converted.$arr[0]."<description><![CDATA[".$result."<br/>".$arr[1];
                }
            }
        // }
    }
    $converted=$converted."</channel></rss>";


$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $_SERVER['REGION'],
    'credentials' => [
        'key'    => $_SERVER['KEY'],
        'secret' => $_SERVER['SECRET']
    ]
]);
$bucketName = $_SERVER['BUCKET'];


try {
    $result = $s3->putObject([
        'Bucket' => $bucketName,
        'Key'    => basename($filename),
        'ContentType' => 'application/xml',
        'Body'   => $converted,
        'ACL'    => 'public-read'
    ]);
    echo "File uploaded successfully. File path is: ". $result->get('ObjectURL') ."\n";
} catch (Aws\S3\Exception\S3Exception $e) {
    echo "There was an error uploading the file.\n";
    echo $e->getMessage();
}
}


function detect($data){
    $url="https://translation.googleapis.com/language/translate/v2/detect?key=AIzaSyAvg4NhVnHVhLaIvdUdTmIzkEFJiWinALk";

    $postdata = http_build_query(
        $data
    );

    $opts = array('http' =>
                  array(
                      'method'  => 'POST',
                      'header'  => 'Content-type:application/x-www-form-urlencoded',
                      'content' => $postdata
                  )
    );

    $context = stream_context_create($opts);

    $json = file_get_contents($url, false, $context);
    $obj=json_decode($json);
    $result=$obj->{'data'}->{'detections'}[0][0]->{'language'};
    return $result;
}

function translate($data){
    $url="https://translation.googleapis.com/language/translate/v2?key=AIzaSyAvg4NhVnHVhLaIvdUdTmIzkEFJiWinALk";

    $postdata = http_build_query(
        $data
    );

    $opts = array('http' =>
                  array(
                      'method'  => 'POST',
                      'header'  => 'Content-type:application/x-www-form-urlencoded',
                      'content' => $postdata
                  )
    );

    $context = stream_context_create($opts);

    $json = file_get_contents($url, false, $context);
    $obj=json_decode($json);
    $result=$obj->{'data'}->{'translations'}[0]->{'translatedText'};
    return $result;

}

?>
