<?php
//todo: make todo list
//------------------
//todo: otimizar tempo de resposta e consumo de recursos em geral
//      proxys parecem muito demorados.. talvez possamos mensurar velocidades deles (rodar speedtest no check) e então classificar
//      testar também se phantom sem proxy desempenha melhor e depois comparar com curl!!
//      ps: proxys americanos sao BEMM mais rapidos.
if ( count($argv) <= 1 ) {
    echo "Usage: $argv[0] suite_name [resume_pid\n";
    exit;
}

require('crawler.utils.php');
require('rb.php');

global $debuglevel;
$debuglevel = 1;

$suite_file = $argv[1];
$crawler = new Crawler( $suite_file );

$crawler->start();
return $crawler->totalitems;

Class Crawler {
    //global configuration (from input file)
    //todo: criar todos os campos possíveis aqui, como exemplo, se possível com comentários
    private $config = array(
                'databases' => array(
                    array('config', 'sqlite:./data/config','crawler','yeswecan',false)
                    ),
                'crawler' => array(
                    'proxy' => false,
                    'proxy_limit' => 12,
                    //todo: transformar o sequential em sequential_suite, ou seja, manter sequencia para o suite inteiro e não só o craw
                    'proxy_mode'  => 'aleatory', //the proxy list should act: aleatory, sequential, sequential_suite
                    'delay' => 1, //wait time in seconds between the scrapes
                    'loop'  => 1, //number of times to repeat scrapers
                    'debug' => 2, //false, //todo: validar funcionalidade?!
                    ),
                'scrapers' => array(),
              );
    //instance config settings
    public $settings = array();
    //total of items crawled in this instance
    public $totalitems = 0;
    //crawler bean instance
    public $dbobj = null;
    //resume process vars (todo)
    public $resume = false, $resumepid = null;
    //array of scrapers which has been already run
    public $scraped = array();
    //internal retry pages scraper
    private $retry = array(
        'pages' => array(
            'error' => array(),
            'success' => array(),
            ),
        'query' => array(
            'error' => array(),
            'success' => array(),
            ),
        'retries' => 0, //pointer for each scraper
        );
    public $Json;
    
    function Crawler( $configfile ) {
        Log::time('crawler');
        
        //startup steps
        $this->loadConfig($configfile);
        $this->startDatabases();
        
        //get process id from cmd-line in order to resume its jobs
        //not tested!!
        if (isset( $argv[2] )) {
            $this->resume = true;
            $this->resumepid = $argv[2];
        }

        //init the database object for the crawler
        $this->saveDbObj('init', $configfile);
        
        //debuglevel: setting is based on the log entry names (further)
        global $debuglevel;
        $debuglevel = $this->settings['debug'];

        register_shutdown_function(array($this, '__destruct'));
        set_time_limit(0);
        
        Log::debug('Crawler', 'New instance running: '. $this->dbobj->pid );
        Log::debug('Crawler/config/json', json_encode($this->config) );
    }

    //crawler object in database
    function saveDbObj( $state, $params=array() ) {
        R::selectDatabase( 'config' );

        switch ($state) {
            case 'init':
                $name = $params;
            
                //initiate and/or resume the object
                //todo: for the cron-resume-all:
                //      figure out if happened another craw after the resumed one date
                //      which has been finished properly
                //      if so set the past to finished too
                //todo: guardar json com configurações (craw e scrap)
                //todo: melhorar query!! os pids se repetem........ é preciso colocar o id a disposição do resume..!

                /*$this->dbobj = R::findOrDispense('omCrawler', 'pid=? ORDER BY id DESC', array( $this->resumepid ));
                $this->dbobj = reset($this->dbobj);
                $this->dbobj->pid = getmypid();*/
                //todo: review
                /*if ( !$this->resume ) {
                    $this->dbobj->status = 'i'; //initiated
                    $this->dbobj->try = 0; //first try - hopefully the only one
                } else {
                    //todo: maxtries and complete resume
                    $this->dbobj->try += 1;
                }
                if ( isset($this->dbobj->totalitems) && $this->dbobj->totalitems > 0 )
                    $this->totalitems = $this->dbobj->totalitems;

                 */

                $this->dbobj = R::dispense('omCrawler');
                $this->dbobj->name = $name;
                $this->dbobj->status = 'i'; //initiated
                $this->dbobj->pid = getmypid();
                $this->dbobj->try = 0; //first try - hopefully the only one

                $this->dbobj->time_start = date('Y-m-d H:i:s');
                
                break;
            case 'finish':
                $this->dbobj->totalitems = $this->totalitems;
            
                if ( $this->dbobj->status == 'i' )
                    $this->dbobj->status = 'f'; //finished
                
                //$this->dbobj->time_end = date('Y-m-d H:i:s');

                break;
            case 'scraperStart':
                $scrpdb = $params;
                $i = count( $this->scraped ); //next scraped array index

                if ($this->retry['retries'] > 0) {
                    //we are on a subscrape | retries is not 0-based
                    $pointer = ($i -1) ."-$this->retry[retries]";
                } else {
                    //normal scrape - parent
                    $pointer = $i;
                }
                
                $this->dbobj->pointer = $pointer; //scraper index to know where it is
                $this->dbobj->ownOmScraper[] = $scrpdb;
                break;
            case 'scraperEnd':
                $ids = $params;
                $scrpdb->count = count($ids);
                $scrpdb->dataids = json_encode( $ids );
                break;
            case 'crawlerError':
                $this->dbobj->pages = json_encode( $this->retry['pages'] );
                $this->dbobj->status = 'e';
            case 'crawlerEnd':
                $this->dbobj->time_end = date('Y-m-d H:i:s');
                break;
        }
        
        if ($this->dbobj)
            R::store($this->dbobj);
    }

    //load a configuration file
    function loadConfig( $file=false ) {
        if (!$file)
            return $this->config;

        $hdl = fopen( $file, 'r' );
        $json = fread( $hdl, filesize($file) );
        $json = json_clean_functions( $json );
        
        //echo $json, "\n";
        //exit;
        
        require 'JSON.php';
        $this->Json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
        $usrconfig = $this->Json->decode($json);
        
        //$usrconfig = json_decode( json_clean($json), true );
        
        //var_dump($usrconfig); exit;
        
        //json error catch
        if ( !$usrconfig ) {
            // Define the errors.
            $constants = get_defined_constants(true);
            $json_errors = array(
                JSON_ERROR_NONE => 'No error has occurred',
                JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
                JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
                JSON_ERROR_SYNTAX => 'Syntax error',
            );
            Log::out($json, 'Crawler/config', 2);
            Log::halt( "Error while parsing config file\nLast error: ". $json_errors[ json_last_error() ]);
        }
        
        //merge configs
        $dbs = $this->config['databases']; //number indexed arrays get overwrite in the replace bellow
        $this->config = array_replace_recursive( $this->config, $usrconfig );
        $this->config['databases'] = array_merge($dbs, $this->config['databases']); //merge dbs array
        $this->settings =& $this->config['crawler']; //link config to the crawler settings

        fclose( $hdl );
    }
    
    //configura bases
    private function startDatabases() {
        foreach ( $this->config['databases'] as $dbsetup ) {
            if (is_array($dbsetup))
                call_user_func_array( 'R::addDatabase', $dbsetup );
        }
    }
    
    //fetch the datascrape, call the scraper parser and store data
    function start() {
    	//grap scrapers from config input
        $scrps = array_filter( $this->config['scrapers'] );
        
        //startup scrapings looking for resume proc
        //todo: pensar melhor nesse resume, devido nova funcionalidade de captar error e fazer na hora
        //sim: pode resumir tanto os erros quanto um processo interrompido (index/pointer)
        //todo: prepareResume() - vai passar ao scrape() somente scrps que precisam ser feitos
        //talvez resume não seja mesmo necessário porque as ferramentas de api e multi scraper dão conta de lidar com os lotes de consulta, sem se desgastar com recuperações (scrapes finalizados devem sempre retornar)
        /*$is = 0; //scraper index
        if ($this->resume) {
        	if ($this->dbobj->pointer > count($scrps) )
        	   Log::halt('Cannot resume process: already finished.');
        	
        	//continue from the last started scraper
            $is = (int) $this->dbobj->pointer;
        }*/
        $is = (int) $this->dbobj->pointer;

        
        for ( $p=0; $p < $this->settings['loop']; $p++  ) {
            //Run the scrapers batch
            $this->totalitems += $this->scrape( $scrps, $is );

           //delay the next scrape
           if ( $p < $this->settings['loop'] && (int) $this->settings['delay'] > 0 )
               sleep( $this->settings['delay'] );
        }
        
        //todo: save the crawler as finished
        $this->saveDbObj('finish');
    }
    
    function scrape( array $scrps, $indexscraper = 0 ) {
        $num_obj = 0; //global (scrps) data count
        
        //loop throught defined scrapers
        for ( $i = 0; $i < count($scrps); $i++ ) {
            $scrp = $scrps[$i];

            //if there's no data is not a scraper
            if ( count($scrp) == 0 || ( !isset($scrp['suite']) && !isset($scrp['data']) ) )
                continue; 
            
            //create Scraper class instance and load proxy
            $scraper = $this->prepareScraper( $scrp, $i );
                        
            //if there's no data to query, should not continue scraping !??
            if ( $scraper->settings['query'] && count($scraper->settings['query']['data']) < 1 ) {
                Log::out("No data to query on $scraper->suite_name", "Crawler/Scraper #$i/Query", 3);
                continue;
            }
            
            //log starting suite
            Log::out("Starting Suite [{$scraper->dbobj->id}]: $scraper->suite_name", "Crawler/Scraper #$i", 2);
            Log::debug("Crawler/scrape/suitejs", $scraper->suitejs, 2);
            
            //temp store some scrape data
            $numitms = 0; $ids = array();
            
            if ( isset($scrp['data']) && isset($scrp['store']) )
            { //user defined scraper data, no fetching
                $numitms = $scraper->store( $scrp['data'] );
            }
            else
            { //fetch data from scraper suite
                //TODO: terminar/testar controle dos erros e retrials. aprimorar log e estruturas de dados, junto com o processo infinito (repete ate finalizar as paginas ou então desiste por numero limite)";
                //todo: melhorar chamadas de metodos.. verificar se é mesmo preciso retornar um valor, ou se seria melhor já seta-lo a um objeto dentro do proprio metodo (fica mais legivel)
                //todo: configurar timeout para chamadas não ficarem travadas
                
                $output = $this->runPhantom( $scraper );

                //echo $output; exit;
                $scraper->load( $output );
                //print_r( $scraper->pages );
                //exit;
                
                //save retrieved rows
                if ( isset( $scrp['store'] ) )
                    $numitms = $scraper->store();
                
                
                //store the scrape in the scraped stack
                if ($this->retry['retries'] == 0)
                    $this->scraped[] = $scrp;

                //todo: refactor (finishScrape)
                //atualiza status das query
                R::selectDatabase( 'config' );
                foreach ($scraper->dbobj->ownOmQuery as $query) {
                    $qryerr = array_msearch($scraper->query['error'], 'id', $query->id);
                    if ( count($qryerr) > 0 )
                        $query->status = 'e';
                    else
                        $query->status = 'f';
                }
                R::store($scraper->dbobj);
                R::storeAll($scraper->dbobj->ownOmQuery);
                
                //re-fetch failed pages if exist any
                $numitms += $this->scrapeErrors( $scraper );
           }

           //todo: total result must be the new itens (not all itens)
           Log::out("Finished {$scraper->suite_name} with $numitms total results", 'Crawler');
           $num_obj += $numitms;

           //associate fetched data with the scrape data object
           $this->saveDbObj('scraperEnd', $scraper->ids);
           
           //delay the next scrape
           if ( $i < count($scrps) && (int) $this->settings['delay'] > 0 )
               sleep( $this->settings['delay'] );
        }
        
        return $num_obj;
    }

    //FERRAMENTA de RETRY - tenta buscar na hora os itens falhos no cliente.
    //todo: fatal error tb precisa de catch, talvez dbobj->status
    private function scrapeErrors( $scraper ) {
        $num_obj = 0;

        //remove last succeed pages from the error stack
        $this->retry['pages']['error'] = array_diff( $this->retry['pages']['error'], $scraper->pages['success'] );
        //add failed pages to error stack
        $this->retry['pages']['error'] = array_unique(array_merge( $this->retry['pages']['error'], $scraper->pages['error'] ));
        //add successfull pages to stack
        $this->retry['pages']['success'] = array_unique(array_merge( $this->retry['pages']['success'], $scraper->pages['success'] ));
        //add failed querys to error stack
        $this->retry['query']['error'] = array_unique(array_merge( $this->retry['query']['error'], $scraper->query['error'] ));
        
        //todo: log/debug scrapeErrors

        //todo: fazer o retry das querys junto (ou separado) das páginas - pensar melhor
        if ( count( $scraper->query['error'] ) > 0 && true==false) {
            //define status das queries como error
            //depois vamos fazer repetição automatica..
            //e por fim precisamos finalizar as que estão corretas (status)
            foreach ( $scraper->query['error'] as $qry ) {
                $qry = json_decode($qry, true);
                $qryrow = array_msearch( $scraper->dbobj->ownOmQuery, 'id', $qryrow['id'] );
                print_r($qryrow);
                exit();
                $qryrow->status = 'e';
                R::store($qryrow);
                $qryrow[''];
            }
        }

        //there's error in scraped pages: make retrial
        if ( count($this->retry['pages']['error']) > 0 ) {
            //todo: delay / sleep
        	//max number of retries per scrape
            if ( $this->retry['retries'] > 7 ) {
                //todo: too much tries
                //error in this crawler on pages ZX
                //log the problem
                
                $this->saveDbObj('crawlerError');
                
                $this->retry['pages']['error'] = array();
                $this->retry['pages']['success'] = array();
                $this->retry['pages']['query'] = array();
                $this->retry['retries'] = 0;
            } else {
            	//todo: refactor prepareReScrape, ScrapeError..
                
                //pega suite original (array ou arquivo)
                if ( is_array($scraper->settings['suite']) ) {
                    $rescrp = $scraper->settings['suite'];
                } else {
                	//todo: buscar suite file e fazer parse das suas configurações
                	// porenquanto será necessário usar configuração em um unico arquivo (suite array)
                	// mas depois podemos usar um parser:
                	//   1) fake js que recebe as chamadas (add e config) convertendo os args em json
                	//   2) parse file string com regex etc
                	$rescrp = array();
                }
                
                //configura novo suite com informações que precisamos passar pro scrape()/prepareScrape();
                $rescrp['suite']['url'] = null;
                $rescrp['suite']['urls'] = $this->retry['pages']['error'];
                $rescrp['suite']['config']['ignoreUrls'] = $this->retry['pages']['success'];
                
                //little delay for system/network recovery
                sleep(5);
                
                //re-scrape error pages
                $this->retry['retries'] += 1;
                $num_obj = $this->scrape( array( $rescrp ) );
                
                //store the scrape in the scraped stack
                $i = count( $this->scraped ) -1;
                if ($this->retry['retries'] == 1) //if first retry
                    $this->scraped[$i] = array( $this->scraped[$i] );
                $this->scraped[$i][] = $rescrp;
            }
        } else {
            //no more errors, clean pointer and stacks
            $this->retry['pages']['error'] = array();
            $this->retry['pages']['success'] = array();
            $this->retry['retries'] = 0;
        }
        
        return $num_obj;
    }
    
    private function prepareScraper( $scrp, $index ) {
        R::selectDatabase( 'config' );

        //initiate the scraper instance
        $scraper = new Scraper( $scrp );
        
        //TODO: review this, review scraper dbobjs (+resume fields)
        //      fazer na hora de completar o resume
        
        //create a new scrape db obj
        $scrpdb = R::dispense( 'omScraper' );
        //$scrpdb->suite = ( is_array($scrp['suite']) ) ? json_encode( $scrp['suite'] ) : $scrp['suite'];
        $scrpdb->suite = $scraper->suite_name;
        $scrpdb->entity = @$scrp['store']['entity'];
        $scrpdb->date = date('Y-m-d H:i:s');
        $scrpdb->try = $this->retry['retries']; //retry pointer of the scrape
        R::store($scrpdb);
        
        //store crawler resume pointer
        $this->saveDbObj('scraperStart', $scrpdb);
        
        $scraper->dbobj = $scrpdb;

        
        //prepare the scraper
        $this->prepareQuery( $scraper );
        $this->prepareSuite( $scraper );
        
        //todo: refactor - suite_name só é gerado no prepareSuite.. melhorar a geração desse objeto e otimizar os saves..!
        $scrpdb->suite = $scraper->suite_name;
        R::store($scrpdb);
        
        //todo: refactor - prepareProxy
        //fetch proxy data
        if ( $this->settings['proxy'] === true ) {
            
            $limit = $this->settings['proxy_limit'];
            $mode = $this->settings['proxy_mode'];
            $except = array();
            
            //sequential proxys should not repeat themselfs in the same crawler
            if ( $mode == 'sequential' ) {
                //find proxys that was used on this craw
                $except = R::getCol('SELECT proxy FROM omScraper WHERE proxy > 0 AND omCrawler_id = ?', array( $scraper->dbobj->omCrawler_id ));
            }
            //sequential_suite do not repeat the proxy for the suite in any time
            if ( $mode == 'sequential_suite' ) {
                //todo: somente scrapings que deram certo (os falhos podem repetir). porem é preciso de alguma forma catalogar esses proxys ruins.. deixar por ultimo
                //find proxys that was used for the suite before
                $except = R::getCol('SELECT proxy FROM omScraper WHERE proxy > 0 AND suite = ?', array( $scraper->dbobj->suite ));
            }
            
            //grab the proxy to be used in this scrape
            $scraper->proxy = Proxy::getProxy( $limit, $mode, $except );
            
            //associate the proxy with the scrape
            $scrpdb->proxy = $scraper->proxy->id;
            R::store($scrpdb);

            
            //agent defined by proxy
            if ( $scraper->proxy instanceof RedBean_OODBBean && !empty( $scraper->proxy->agent ) ) {
                $suite['config']['pageSettings']['userAgent'] = $scraper->proxy->agent;
            }

        //user-defined proxy (string)
        } elseif ( !empty($this->settings['proxy']) ) {
            
            if ( Proxy::checkProxy($proxy) )
                $scraper->proxy = $proxy;
            else
                $scraper->proxy = null; //proxy is dead (timeout)
                
        }


        return $scraper;
    }
    
    private function prepareSuite( &$scraper ) {
        //get scraper config array
        $scrp = $scraper->settings;

        //prepare suite if is a javascript
        if (is_array( $scrp['suite'] )) {
            //basic js suite
            $suite = array(
                'url'=>'',
                'scraper'=>'function(){ return {}; }',
                'config'=> array(
                    'pageSettings'=> array(
                        'userAgent'=>'Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:13.0) Gecko/20100101 Firefox/13.1',
                        'loadImages'=>false,
                        //'timeoutInterval'=>
                        )
                    )
                );
            //merge base suite with the one user-defined
            $suite = array_replace_recursive( $suite, $scrp['suite'] );

            //prepare the api query
            if (isset( $scrp['api']['caller'] )) {
                $suite['url'] = $scrp['api']['caller'];
                //$this->prepareApi($scraper);
            }
            //api/query data
            //todo: rename variable to api e refatorar tool - unir query e api pois fazem parte do mesmo esquema
            $suite['query'][] = is_array( $scrp['query']['data'] ) ? $scrp['query']['data'] : array();
            $suite['query'][] = $scrp['api'];

            //pjs config
            $suitejs_config = is_array($suite['config']) ? $suite['config'] : array();
            $suitejs_config = json_encode( $suitejs_config );
            
            //pjs suite (sem config)
            $suitejs = array_diff_assoc( $suite, array( 'config'=>array() ) );
            $suitejs = json_encode( $suitejs );

            //unquote js functions
            $suitejs = preg_replace('/:"(function.*?\})"([\,\}\]])/', ':$1$2', $suitejs);
            $suitejs = stripcslashes($suitejs);
            
            //parse suite options to javascript notation
            //todo: make possible multiple suites in one scrape (multiple addSuite) (arrayfy suite var in json config)
            $suitejs = "pjs.addSuite($suitejs);";
            $suitejs_config = "pjs.config($suitejs_config);";
            
            //print_r($suite);
            //exit;

            $scraper->suite = json_encode($suite);
            $scraper->suitejs = $suitejs . $suitejs_config;

            if ( !$scraper->suite_name )
                $scraper->suite_name = @(is_array( $scrp['suite']['url'] )) ? implode(',', $scrp['suite']['url']) : $scrp['suite']['url'] ;
        } else {
            //string suite - is a file with the js suite
            $cmd = "phantomjs core/js/pjs-wrapper.js $scrp[suite]";

            //grab the suite for parsing
            $output = shell_exec($cmd);
            $json = json_decode($output, true);

            //var_dump($output); exit;
            //todo: catch parse error on null json
            
            $suite_bkp = $scraper->settings['suite'];

            $scraper->settings['suite'] = $json[0][0];
            $scraper->settings['suite']['config'] = $json[1];
            $scraper->suite_name = $scrp['suite'];
            $this->prepareSuite($scraper);
            
            $scraper->settings['suite'] = $suite_bkp;
        }
    }

    //todo: padronizar passagem de parametros (instancia ou array?)
    // $scrp means it is the array from settings
    // $scraper means it is a Scraper class instance
    private function prepareQuery( &$scraper ) {
        //get scraper config array
        $scrp = $scraper->settings;
        $query = $scrp['query'];

        if ( $query ) {
            if (isset( $query['data'] ))
            {
                //user-defined query data
                $scraper->settings['query']['data'] = $query['data'];

                //todo: gravar no banco, associar crawler, etc - refactor
            }
            else {
                //todo:comentar o motivo de cada campo (levar para settings la em cima)
                
                $database = $query['database'];
                $entity = @$query['entity'];
                $sql = ( isset($query['sql']) ? $query['sql'] : '1 = 1' ) ;
                $values = @(array) $query['values'];
                $limit = ( isset($query['limit']) ) ? $query['limit'] : null;
                $fields = ( isset($query['fields']) ) ? $query['fields'] : array();
                $sanitize = ( isset($query['sanitize']) ) ? $query['sanitize'] : false;

                //todo: rever esquema de paginação query OK
                //      status inicializado tb nao entra na conta OK
                //      apenas contar e ordenar não garante que teremos os itens certos. é preciso buscar diretamente ou empregar regra que funcione. OK
                //      qryobj anexo ao crawler ou scraper? OK
                 //ideia: contar independente do status (ou seja, rodar todos itens ate o fim, em ordem) e depois (ou memos durante) estar pegando os itens que ficaram pra tras com erros etc.
                //todo: depois de buscar todo o offset busca os itens com status nao finalizado
                //ideia: permitir aqui tb o loop de querys (mais de um dicionario)

                //fetch the proper offset for this instance
                if ( $limit ) {
                    $adapter = R::$toolboxes['config']->getDatabaseAdapter();

                    try {
                        //the offset is the amount of runned queries
                        $offset = (int) $adapter->getCell("SELECT count(1)  
                                                       FROM omQuery q
                                                         INNER JOIN omScraper s
                                                            ON s.id = q.omScraper_id
                                                         INNER JOIN omCrawler c
                                                            ON c.id = s.omCrawler_id
                                                           AND c.name = '{$this->dbobj->name}'
                                                       GROUP BY c.name");
                        $sql .= " LIMIT $limit OFFSET $offset";
                    } catch ( Exception $e ) {
                        //first run?
                        $sql .= " LIMIT $limit OFFSET 0";
                    }
                }
                
                if ($entity) {
                    //fetch query db objs
                    $qrydb = R::$toolboxes[$database]->getRedBean();
                    $beans = $qrydb->find( $entity, array(), array($sql, $values) );
                } else {
                    //pure sql query
                    $qryad = R::$toolboxes[$database]->getDatabaseAdapter();
                    $beans = $qryad->get($sql, $values);
                }
                
                //filter selected fields & transform to array
                //also sanitize query fields
                $queries = array();
                foreach ( $beans as $row ) {
                    if ( $row instanceof RedBean_OODBBean )
                        $row = $row->export();

                    if ($sanitize) {
                        foreach ($row as $key=>$val)
                            $row[$key] = Convert::sane_text($val);
                    }

                    if ( count($fields) > 0 ) {
                        $fields[] = 'id';
                        $fields = array_unique($fields);

                        //its mandatory to have a id field to identify the query obj
                        $queries[] = array_intersect_key($row, array_flip($fields));
                    } else {
                        $queries[] = $row;
                    }
                }

                //set the scraper query data array
                $scraper->settings['query']['data'] = $queries;

                //arrange query array to be saved to database
                $query_rows = array_map(function($row) {
                                            return array(
                                                'data' => json_encode($row),
                                                'status' => 'i',
                                                );
                                        }, $queries);

                                        //statuses: i: initialized, e: error, f: finished
                                        
                R::selectDatabase('config');
                //associate query beans with this scraper
                $query_beans = array();
                foreach ( $query_rows as $row ) {
                    $bean = R::dispense('omQuery');
                    $bean->import($row);
                    $query_beans[] = $bean;
                }
                $scraper->dbobj->ownOmQuery = $query_beans;

                //save query config objs to db
                R::storeAll($query_beans);
                R::store($scraper->dbobj);
            }
        } else {
            $scraper->settings['query'] = null;
        }
        //case in which user specify the query data
        //return formated fields
    }
    
    //todo: prepara parametros API como caller, destiny e outros via php-array
    private function prepareApi( &$scraper ) {
        //print_r($scraper);
        $scraper->suite['url'] = $scraper->settings['api']['caller'];
    }

    private function runPhantom( $scraper ) {
        Log::time('phantom');
        $params = array(
            'load-images' => 'no',
            'max-disk-cache-size' => '100101',
            );
        
        //Define proxy phantom setup
        $proxy = $scraper->proxy;
        if ( $proxy instanceof RedBean_OODBBean ) {
            $params = array_replace( $params, array('proxy' => $proxy->server,
                                        'proxy-type' => $proxy->type)
                );
        } elseif ( !empty($proxy) ) {
            $params = array_replace( $params, array('proxy' => $proxy,
                                        'proxy-type' => 'http')
                );
        }
        
        //configure suite to be sent
        //todo: se string for muito grande criar arquivo temporario!! | $ getconf ARG_MAX
        //  expr `getconf ARG_MAX` - `env|wc -c` - `env|wc -l` \* 4 - 2048
        // http://www.in-ulm.de/~mascheck/various/argmax/
        if ( isset($scraper->suitejs) )
            $suite = $scraper->suitejs;
            //$suite = escapeshellarg($scraper->suitejs);
        else
            $suite = $scraper->suite;
        //print_r($scraper->suitejs);

        //cria arquivo suite temporario
        $fname = "/tmp/". md5($scraper->suite_name . time());
        $hdl = fopen("$fname", 'w');
        fwrite($hdl, $suite);
        fclose($hdl);
        
        $pjscrape = 'core/pjscrape/pjscrape.js';
        
        $cmd = "phantomjs --". array_implode('=', ' --', $params) ." $pjscrape $fname";

        //echo $cmd, "\n\n"; exit;

        //todo: achar uma forma de: 1) trazer o resultado do exec em real time; 2) associar o pid do phantom com o php; 3) assim tentar encontrar processos stalled e dar solução/debug/retry..
        //ref: http://stackoverflow.com/questions/1281140/run-process-with-realtime-output-in-php

        //exec the phantom command and get outputs
        Log::debug('Crawler/Phantomjs/cmd', $cmd );
        $output = shell_exec($cmd);

        //apaga arquivo temporario
        //unlink($fname);

        Log::timeEnd('phantom');
        
        //Log::debug('Crawler/Phantom', $output ."\n" );
        return $output;
    }
    
    
    function __destruct() {
        //close databases
        foreach ( R::$toolboxes as $toolbox ) {
            $toolbox->getDatabaseAdapter()->close();
        }
    	
        //save end time
        if (isset( R::$toolboxes['config'] ))
        $this->saveDbObj('crawlerEnd');
        
        Log::timeEnd('crawler');
    }
}

Class Scraper {
	//scraper configuration
    public $settings = array(
		        'suite' => array(), //suite complete configuration
		        'format' => array(), //rows before store formating
		        'store' => array(), //database store settings
		        'query' => array(), //data query settings
		        'api' => array(), //api query settings
		    );
		    
    //modified data ids, log and data colected
    public $ids = array();
    private $numitems, $log, $data=array();
    
    //scraped pages stack
    public $pages = array(
                        'error'=>array(),
                        'success'=>array(),
                        );
    public $query = array(
                        'error'=>array(),
                        'success'=>array(),
                        );
                        
    //db obj instance and proxy instance/server
    public $dbobj = null, $proxy;
    //suite string and javascript inject
    public $suite, $suitejs;
    //suite readable name
    public $suite_name='';
    
    function Scraper( $config, $output=null ) {
        $this->settings = array_replace_recursive( $this->settings, $config );
        //Log::debug('Scraper/settings/json', json_encode($this->settings));
        
        if ($output)
            $this->load( $output );
    }
    
    //loads and parses output
    function load( $outputstr, $merge=false ) {
        $itemscount = 0;
            
        //loads clean json
        echo $outputstr;
        $output = $this->loadPjscrape( $outputstr );
        //$json = json_clean( $output );
        //$data = json_decode($json, true);
        $data = json_decode($output, true);
        
        Log::debug('Scraper/load', "Parsed Json Count: ".count($data) );
        
        //json error catch
        if (is_null( $data )) {
            // Define the errors.
            $constants = get_defined_constants(true);
            $json_errors = array(
                JSON_ERROR_NONE => 'No error has occurred',
                JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
                JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
                JSON_ERROR_SYNTAX => 'Syntax error',
            );
            Log::debug('Scraper/output', $outputstr);
            Log::debug('Scraper/parsejson', $output/*$json*/);
            Log::halt( "Error while parsing scraper data\nLast error: ". $json_errors[ json_last_error() ], 'Scraper');
        }
        
        //todo: use merge also in the store method (for cllass obvisly)?
        //      casar o uso das duas alternativas (validar)
        if ( $merge )
            $this->data = array_merge_recursive( $data );
        else
            $this->data = $data;
    }
    
    //clean-up pjscrape output parsing its return
    function loadPjscrape( $output ) {
        //pj logs
        $logs = array(); $logerr = array();
        //failed pages
        $errpages = array();
        //success pages
        $scspages = array();
        $pages = array(
            'success' => array(),
            'error' => array(),
        );
        $query = array(
            'success' => array(),
            'error' => array(),
        );
        
        //commom log callback
        $logcb = function( $mtch ) use ( &$log, &$pages ) {
            $log[] = $mtch[0];
            
            //store successfull pages
            preg_match( '/Scraping (.+)$/', $mtch[0], $m );
            if ( count($m) == 2 ) {
                $pages['success'][] = $m[1];
            }
            
            return '';
        };
        //error/alert log callback
        $errcb = function( $mtch ) use ( &$log, &$logerr, &$pages ) {
            $logerr[] = $mtch[0];
            $log[] = $mtch[0];
            //Till now, we have in pjs:
            //3 ERRORS
            //- Page did not load (status)*
            //- Page not found (404) -> sad, but nothing to do
            //- Page error code (status)*
            //2 ALERTS
            //- Timeout after (waitFor)* (todo, check)
            //- phantom->page.onAlert
            //
            //- * we must care
            //TODO: store error codes, log
            
            preg_match( '/Page did not load \(status=(.+)\): (.+)$/', $mtch[0], $merr );
            if ( count($merr) == 3 ) {
                $pages['error'][] = $merr[2];
            }
            preg_match( '/Page error code (.+) on (.+)$/', $mtch[0], $merr );
            if ( count($merr) == 3 ) {
                $pages['error'][] = $merr[2];
            }
        };
        //todo: callbak para FATAL ERROR (fail function)
        //client/console origin messages
        $clicb = function( $mtch ) use ( &$log, &$query ) {
            $log[] = $mtch[0];

            //store failed query items
            preg_match( '/Item Failed: (.+)$/', $mtch[0], $m );
            if ( count($m) == 2 ) {
                $query['error'][] = $m[1];
            }

            return '';
        };
        
        $output = preg_replace_callback('/^(ERROR|!).*$/im', $errcb, $output); //pjs > log.err && log.alert
        $output = preg_replace_callback('/^(\* CLIENT: ).*$/m', $clicb, $output);
        $output = preg_replace_callback('/^\*.*$/m', $logcb, $output); //pjs > log.msg
        //$output = preg_replace_callback('/^(!|CLIENT|Timeout).*$/im', $errparse, $output); //pjs ERROR: OUTPUT
        //$output = preg_replace_callback('/(\* Saved (\d+) items\n)/', $itemscb, $output);
        $output = preg_replace('/\n/', '', $output); //blank lines
        
        $this->pages = $pages;
        $this->query = $query;
        
        return $output;
    }
    
    //store data into database
    function store( $data=null, $merge=false ) {
        if ($data)
            if ( $merge )
                $this->data = array_merge_recursive( $data );
            else
                $this->data = $data;
        
        Log::time('store');
        
        R::selectDatabase( $this->settings['store']['database'] );
        
        if ( count((array) $this->data) > 0 ) { 
            //todo:transaction
            //R::begin();
            
            //importa output-data procurando por item duplicado
            $bean = $this->settings['store']['entity'];
            $unique = @$this->settings['store']['unique'];
            $rows = array();
            
            //todo: unique multicampos (array.foreach)
            
            foreach ( $this->data as $datarow ) {
                $row = null;
                $findp = array( $bean ); //find params
                
                //search for unique / no duplicate
                if ( $unique && $datarow[$unique] ) { 
                    $findp[] = "$unique = ?  ORDER BY id DESC";
                    $findp[] = array( $datarow[$unique] );
                }
                $row = call_user_func_array('R::findOrDispense', $findp);
                $row = reset($row); //get first
                    
                //datarow insert/update
                $row->import( $datarow );
        
                //format row using user-defined actions
                $this->formatRow( &$row );
                
                $rows[] = $row;
            }
            
            //armazenas rows e guarda ids
            $ids = R::storeAll($rows);
            $this->ids = array_merge( $this->ids, $ids );

            
            //commit changes
            //R::commit();
            
            Log::timeEnd('store');
            
            return count($ids);
            
        } else {
            Log::out( "Error while storing data. No data present" );
        }
    }
    
    private function formatRow( &$row ) {
        try {
            foreach ( (array) @$this->settings['format'] as $rowfmt ) {
                eval( "$rowfmt;" );
            }
        } catch( Exception $e ) {
            Log::out( "Error formating scrape data\n". $e->getMessage(), 'Scraper/format', 2 );
        }
    }
}

Class Proxy {
    
    static function checkProxy( $host_port, $timeout=10 ) {
        list($host, $port) = explode(':', $host_port);
        $fsock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if ( ! $fsock ) {
            return FALSE;
        } else
            return TRUE;
        //TODO: fazer trial também de uso do proxy: será que ele vai abrir nossa pagina (scrape)?
    }
    
    //todo: associate the proxy with a user agent, so a server is always with the same agent name
    //  make a agents list and randomly match with the proxy
    //  on the scrape!!
    static function getProxy( $maxhours, $mode='aleatory', $except=array() ) {
        R::selectDatabase('config');
        $sql = '1';
        $params = array();

        //limit proxys by the age they were online
        if ($maxhours > 0) {
            $date_limit = mktime() - ( 60*60*$maxhours );
            $sql = 'date > ? ';
            $params[] = $date_limit;
        }
        
        //proxy consume sequence mode
        if ($mode == 'aleatory') {
            $sql .= ' ORDER BY RANDOM()';
        } else { //sequential and sequential_suite
            //$except = array_filter($except);
            $sql .= ' AND id NOT IN ('. implode(',', $except) .') ORDER BY date DESC';
        }

        //match the proxy
        $proxy = R::findOne('proxy', $sql, $params);

        if (!$proxy)
            throw new Exception('Proxy não encontrado');
        
        //check if is a valid proxy
        if ( ! self::checkProxy($proxy->server) ) {
            $proxy->fail = ((int) $proxy->fail)+1;
            R::store($proxy);
            
            Log::out('Failed to resolve '. $proxy->server, 'Proxy/check');
    
            if ($proxy->fail >= 5)
                R::trash( $proxy );

            //search another proxy (online)
            return self::getProxy( $maxhours );
        }
        //renew proxy date/time
        $proxy->date = mktime();
        R::store($proxy);

        return $proxy;
    }
}

Class Log {
    //todo: change the args order to match debug func - ($hierarchy, $msg, $level)
    static function out( $msg, $hierarchy='Crawler', $writelevel=1 ) {
        $output = array();
        $level=0;
        
        //recursively findout the message level, by the hierarchies suplied
        foreach ( preg_split('/\//', "$hierarchy\/") as $path ) {
            $level++;
        }
        $level--;
        
        $output[] = "######################################";
        $output[] = "# $hierarchy <".date('Y-m-d H:i:s').">";
        $output[] = "####";
        
        //convert msg to array of lines
        if ( !is_array($msg) )
            $msg = preg_split('/\n/', "$msg\n");
        
        array_push($output, $msg[0]);

        //tabulate each line
        for ($x=0; $x < count($output); $x++) {
            $line = $output[$x];
            $line = @str_pad( $line, $level*2, null, STR_PAD_LEFT );
            $output[$x] = $line;
        }
        
        self::store( $output );
        //should write this log?
        if ( (int) $writelevel >= $level )
            self::write( $output );
    }
    
    static function write( $output ) {
        //echoe the msg
        echo implode( "\n", $output ), PHP_EOL;
    }
    
    static function halt( $msg ) {
        Log::out( $msg );
        die;
    }
    
    static function store($lines) {
        //store crawler logs
        global $crawler_logs;
        $crawler_log = array_merge( (array) $crawler_logs, (array) $lines );
    }
    
    static function time( $label ) {
        //stores current time in global
        global $log_timer;
        $log_timer[$label] = microtime(true);
    }
    static function timeEnd( $label ) {
        //shows diference from time
        global $log_timer;
        if (isset( $log_timer[$label] )) {
            $total = microtime(true) - $log_timer[$label];
            self::debug("Timer $label", "$label lasts $total seconds" );
            unset( $log_timer[$label] );
        }
    }
    static function debug( $section, $msg ) {
        global $debuglevel;
        self::out( $msg, $section, $debuglevel );
    }
}
