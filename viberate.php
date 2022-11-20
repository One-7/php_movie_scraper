<?php
    require __DIR__ . '/vendor/autoload.php';
    #http://localhost/viberate.php
    #https://movieweb.com/movies/2021/ && ../movie/*

    use GuzzleHttp\Client;
    use GuzzleHttp\Pool;
    use GuzzleHttp\Psr7\Request;
    use GuzzleHttp\Psr7\Response;
    use Symfony\Component\DomCrawler\Crawler;
    use Symfony\Component\CssSelector\CssSelectorConverter;

    $client = new Client([
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'authority' => 'movieweb.com',
            'method' => 'GET',
            'scheme' => 'https',
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'accept-encoding' => 'gzip, deflate',
            'accept-language' => 'sl-SI,sl;q=0.9,en-GB;q=0.8,en;q=0.7',
            'cache-control' => 'max-age=0',
        ],
        'base_uri' => 'https://movieweb.com/movies/2021/'
    ]);

    $movies =[];

    function toFile($movies){
        $json = fopen("movies.json","w") or die("Can't write to file...");
        fwrite($json, $movies);
        fclose($json);

        return "Written succesfully.";
    }

    function handleOK(Response $response, $index){
        global $urls;
        printf("success: %s\n", $urls[$index]);
    }

    function handleFail($reason, $index){
        printf("failed: %s\n",$reason);
    }
    function scrape($urls,$good,$bad){
        $requests = array_map(function ($url){
            return new Request('GET', $url);
        }, $urls);
        global $client;
        $pool = new Pool($client, $requests, [
            'concurrency' => 5,
            'fulfilled' => $good,
            'rejected' => $bad,
        ]);
        $pool->promise()->wait();
    }

    function parseMovie(Response $response, $index){
        $tree = new Crawler($response->getBody()->getContents());

        $nodeGenre = $tree->filterXPath('//div/dt[contains(text(),"Main Genre:")]/following-sibling::dd/*');
        $nodeTitle = $tree->filterXPath('//h1[contains(@class,"listing-title")]');
        $nodeDate = $tree->filterXPath('//div/dt[contains(text(),"Release Date:")]/following-sibling::dd');
        $nodeImg = $tree->filterXPath('//div[contains(@class,"responsive-img  img-tag-poster-portrait")]');

        if(count($nodeGenre) != 0){
            $genre = $tree->filterXPath('//div/dt[contains(text(),"Main Genre:")]/following-sibling::dd/*')->text();
        }
        else {
            $genre = "Data missing!";
        }

        if(count($nodeTitle) != 0){
            $title = $tree->filterXPath('//h1[contains(@class,"listing-title")]')->text();
        }
        else{
            $title = "No title found.";
        }

        if(count($nodeDate) != 0){
            $date = $tree->filterXPath('//div/dt[contains(text(),"Release Date:")]/following-sibling::dd')->text();
        }
        else {
            $date = "Release date unknown.";
        }

        if(count($nodeImg) != 0){
            $img = $nodeImg->attr('data-img-url');
        }
        else {
            $img = "No image.";
        }

        $movie=[
            'title' => $title,
            'date' => $date,
            'genre' => $genre,
            'img' => $img,
        ];

        global $movies;
        array_push($movies,$movie);
    }

    $response = $client->request('GET');
    $tree = new Crawler($response->getBody()->getContents());#spawns crawler 1
    $urls = $tree->filterXPath('//div[contains(@class,"database-img-link")]/a')->each(function ($node,$i) {return $node->attr('href');});#generates urls for every movie

    scrape($urls,'parseMovie','handleFail');

    $try = toFile(json_encode($movies,JSON_PRETTY_PRINT));
    echo $try;
?>