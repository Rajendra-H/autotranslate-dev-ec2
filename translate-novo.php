<?php
require './autoload.php';
use Aws\S3\S3Client;

// $input_names = array('AutoTranslate-NF1','AutoTranslate-NF2');
// $keys = array('606554322e561d067b9009f4','6065547e85a2a4017b61c728');

$input_names = array('nnpl','industry','Disease_Biopharma','competitors','Disease_Diabetes_Obesity');
$keys = array('5c0f6d3422665b04b2515f63','6049a9b5c5553532b3d5d743','5c4e4dd37a9edffd51366110','5c0f6d9e22665b04b2515f64','5c0f6e2b3bca967978986832');


for ($num=0; $num<count($keys); $num++){
    $date='';

    $filename="translate-$input_names[$num].xml";


    $file=file_get_contents("https://app.meltwater.com/gyda/outputs/$keys[$num]/rendering?apiKey=570b21ad1f090138bfdb1f49&type=html");


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
