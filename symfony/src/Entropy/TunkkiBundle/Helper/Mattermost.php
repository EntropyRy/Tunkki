<?
namespace Entropy\TunkkiBundle\Helper;

class Mattermost {
    protected $container;

    public function __construct($container) {
        $this->container = $container;
    }
    public function SendToMattermost($text, $Item = null, $username = null)
    {
        $xcURL = $this->container->getParameter('mm_tunkki_hook');
        $botname = $this->container->getParameter('mm_tunkki_botname');
        $botimg = $this->container->getParameter('mm_tunkki_img');
        $add_url = $this->container->getParameter('mm_add_url');

        $curl = curl_init($xcURL);
        $payload = '{"username":"'.$botname.'", "icon_url":"'.$botimg.'","text":"'.$text.'"}';
        $cOptArr = array (
            CURLOPT_URL => $xcURL,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1
        );
        $rc = curl_setopt_array ($curl, $cOptArr);
        $rc = curl_setopt ($curl, CURLOPT_POSTFIELDS, http_build_query (array ('payload' => $payload)));
        $rc = curl_exec ($curl);
        if ($rc == false){
            curl_close ($curl);
        }
    }
}
