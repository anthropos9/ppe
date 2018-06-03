<?php
namespace Ppe;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

/**
 * Class to scrape recipes from Pepperplate
 * and save them in a usable format for import into other services
 */
class Export
{
    private $output = __DIR__ . '/../output/';
    private $crawler; // DomCrawler\Crawler object
    private $twig; // Twig object
    private $client; // Guzzle client
    private $jar; // Guzzle CookieJar
    private $config; // Array from YAML file

    public function __construct()
    {
        $this->config = Yaml::parseFile(__DIR__ . '/../config/config.yml');

        $loader     = new Twig_Loader_Filesystem(__DIR__ . '/views');
        $this->twig = new Twig_Environment($loader, array(
            'debug' => true,
            // 'cache' => '/path/to/compilation_cache',
        ));

        $this->twig->addExtension(new Twig_Extension_Debug());

        $this->client = new Client(['base_uri' => 'https://www.pepperplate.com']);
        $this->jar    = new CookieJar;

    }

/*    public function reformat($dir = 'recipes', $format = 'txt')
{
$files = scandir($this->output . $dir);

foreach ($files as $f) {

if (substr($f, 0, 1) != '.') {
$html = file_get_contents($this->output . 'recipes/' . $f);

$filename = $this->createFileName($format, $f);

$recipe  = $this->crawl($html);
$tmpl    = $this->twig->loadTemplate('pte.twig');
$content = $tmpl->render($recipe);

$this->save($filename, $content);

}

}

}*/

    /**
     * Log into Pepperplate
     * @param  string $un email of the user logging in
     * @param  string $pw password of the user logging in
     * @return void
     */
    public function login($un, $pw)
    {
        $this->message('Logging In');
        $login = $this->client->request('POST', '/login.aspx', [
            'form_params' => [
                'ctl00$cphMain$loginForm$tbEmail'    => $un,
                'ctl00$cphMain$loginForm$tbPassword' => $pw,
                '__VIEWSTATE'                        => '/wEPDwUKLTcxOTM1MDY3Mw9kFgJmD2QWAgIBD2QWBmYPFgIeB1Zpc2libGVoZAIBDxYCHwBnZAIFD2QWAgIBD2QWAgIBD2QWAmYPZBYCAgEPFgIfAGhkGAEFHl9fQ29udHJvbHNSZXF1aXJlUG9zdEJhY2tLZXlfXxYBBSRjdGwwMCRjcGhNYWluJGxvZ2luRm9ybSRjYlJlbWVtYmVyTWX6+EFLFMRKbfydmpUj4wAPc7mvB44zvf0PSqv5gYc/oQ==',
                '__VIEWSTATEGENERATOR'               => 'C2EE9ABB',
                '__EVENTVALIDATION'                  => '/wEdAAa/1rXdVU0+E4I6qe/8/1vr5NjnQnV3ACakt+OFoq/poIk+G0F2hkBAuVGSTeHfUEPAXUaOb/COCTyxdHOCu+1TWS9Byv/QKTlj8oYJ3PuJaAwq+cY+TuM+f6PEOa5kpFdLxoWu1SzyQ+dSe4wMXUj8COE0cW4aUjyR8doM83m83w==',
                "__EVENTTARGET"                      => 'ctl00$cphMain$loginForm$ibSubmit',
            ],
            'cookies'     => $this->jar,
        ]);

        $res = $login->getBody();

    }

    /**
     * Get a list of recipes from Pepperplate
     * @return array Array of recipe details
     */
    public function getRecipes()
    {
        $this->message('Getting number of recipes');
        $jsonReq = [
            "ingredients" => true,
            "MessageType" => "getsearchresults",
            "page"        => 0,
            "pagesize"    => 20,
            "text"        => '',
            "title"       => true,
        ];
        $req = $this->client->request('POST', '/search/default.aspx/getsearchresults', [
            'json'    => $jsonReq,
            'cookies' => $this->jar,
        ]);

        $resp         = $req->getBody();
        $json         = json_decode($resp);
        $totalRecipes = $json->d->TotalResults;

        $jsonReq['pagesize'] = $totalRecipes;

        $this->message('Getting list of recipes');
        $req2 = $this->client->request('POST', '/search/default.aspx/getsearchresults', [
            'json'    => $jsonReq,
            'cookies' => $this->jar,
        ]);

        $resp2 = $req2->getBody();
        $json2 = json_decode($resp2);

        return $json2->d->Items;
    }

    /**
     * Process the list of recipes loading each recipe
     * then rendering in the chosen format
     * @param  array $list Array of recipes
     * @return void
     */
    public function download($list)
    {
        $base = $this->output . '/clean/';

        foreach ($list as $item) {
            $this->message('Processing ' . $item->Title);
            $req = $this->client->request('GET', '/recipes/view.aspx?id=' . $item->Id, ['cookies' => $this->jar]);

            $filename = $this->toAscii($item->Title) . '.' . $this->config['output_format'];

            $html = (string) $req->getBody();

            $recipe = $this->crawl($html);

            $tmpl    = $this->twig->loadTemplate($this->config['template']);
            $content = $tmpl->render($recipe);

            $this->message('Saving ' . $item->Title);
            $this->save($filename, $content);
        }

        $this->message('Done');

    }

    /**
     * Crawl the recipe page for details to be exported
     * @param  string $html Webpage to be crawled
     * @return array       Array of recipe details
     */
    public function crawl($html)
    {
        $recipe = [
            'title'       => '',
            'description' => '',
            'prep_time'   => '',
            'cook_time'   => '',
            'serves'      => '',
            'source'      => '',
            'tags'        => false,
            'ingredients' => '',
            'directions'  => '',
        ];

        $this->crawler = new Crawler($html);

        $recipe['ingredients'] = $this->getIngredients();
        $recipe['directions']  = $this->getDirections();
        $recipe['tags']        = $this->getTags();

        $recipe['title']       = $this->getNode('cphMiddle_cphMain_lblTitle');
        $recipe['source']      = $this->getLinkNode('cphMiddle_cphSidebar_hlOriginalRecipe');
        $recipe['prep_time']   = $this->getNode('cphMiddle_cphMain_lblActiveTime');
        $recipe['cook_time']   = $this->getNode('cphMiddle_cphMain_lblTotalTime');
        $recipe['description'] = $this->getNode('cphMiddle_cphMain_lblDescription');
        $recipe['serves']      = $this->getNode('cphMiddle_cphMain_lblYield');
        $recipe['notes']       = $this->getNode('cphMiddle_cphMain_lblNotes');

        return $recipe;

    }

    /**
     * Save the recipe
     * @param  string $filename filename for the exported recipe
     * @param  array $content  recipe details
     * @return void
     */
    private function save($filename, $content)
    {
        $h = fopen($this->output . 'clean/' . $filename, 'w');
        fwrite($h, $content);
        fclose($h);
    }

    /**
     * Process the ingredients from a recipe
     * @return string ingredients list
     */
    private function getIngredients()
    {
        $ingredients = $this->crawler->filter('.inggroups')->each(function (Crawler $node, $i) {
            return $node->text();
        });

        if (!empty($ingredients[0])) {
            $ing = trim(str_replace("  ", "", $ingredients[0]));
            $ing = preg_replace("/[\r\n]{3,}/", "^^", $ing);
            $ing = preg_replace("/[\r\n]+/", " ", $ing);
            $ing = str_replace("^^", "\n", $ing);

            return $ing;
        } else {
            return false;
        }

    }

    /**
     * Get a node if it exists
     * @param  string $id the ID of the node being pulled
     * @return string     The processed node or FALSE
     */
    private function getNode($id)
    {
        $node = $this->crawler->filter('#' . $id);

        if ($node->count() > 0) {
            return $node->text();
        } else {
            return false;
        }

    }

    /**
     * Get the HREF attribute of a link node
     * @param  string $id node ID
     * @return string     The processed node or FALSE
     */
    private function getLinkNode($id)
    {
        $node = $this->crawler->filter('#' . $id);

        if ($node->count() > 0) {
            return $node->attr('href');
        } else {
            return false;
        }

    }

    /**
     * Pull and process the directions for the recipe
     * @return string The processed directions
     */
    private function getDirections()
    {
        $directions = $this->crawler->filter('.dirgroups')->each(function (Crawler $node, $i) {
            return $node->text();
        });

        if (!empty($directions[0])) {
            $dir = trim(str_replace(["  "], "", $directions[0]));
            $dir = preg_replace("/[\r\n]+/", "\n", $dir);

            return $dir;
        } else {
            return false;
        }

    }

    /**
     * Get the tags from the recipe then explode into an array
     * @return array List of tags
     */
    private function getTags()
    {
        $tags    = [];
        $rawTags = $this->crawler->filter('.tags > span.text');

        if (!empty($rawTags->count() > 0)) {
            $tmp = explode(",", $rawTags->text());

            foreach ($tmp as $t) {
                $tags[] = trim($t);
            }

        }

        return $tags ?? false;
    }

/*    private function createFileName($ext, $original)
{
$bits = explode(".", $original);
array_pop($bits);
$bits[] = $ext;

return implode(".", $bits);
}*/

    /**
     * Convert a string to a filename safe string
     * @param  string $str       string to be converted
     * @param  array  $replace   characters to be replaced with a space
     * @param  string $delimiter connecting delimiter
     * @return string            reformatted string
     */
    public function toAscii($str, $replace = array(), $delimiter = '-')
    {

        if (!empty($replace)) {
            $str = str_replace((array) $replace, ' ', $str);
        }

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

        return $clean;
    }

    /**
     * Echo a message to the screen
     * @param  string $msg message to be printed
     * @return void
     */
    public function message($msg)
    {
        echo $msg . PHP_EOL;
    }

}
