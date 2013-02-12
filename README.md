omCrawler
=========

Headless webkit data scraper based on phantomjs

Current status
-----

**Beta/Development**
I have used it several times but havent had the time to fully complete my design goal.

Goals
-----

Collect, format and store data from any possible source.
Initial requirement was to collect data from websites within the browser perspective (ajax based), but the goal is to be a definitive crawler application for the most usual scenarios and to offer tools for making the job more concise/easier.

Usage
-----

Define a crawler instance in the suites path like the example bellow (suites/proxy.json)
	{
	    name: 'Proxy Scrapper',
	    databases: [
	        //[name, strDsn, strUser, strPass, boolFrozen], //RedBean database!
	        //['config', 'sqlite:./data/config','crawler','yeswecan',0], //Config is the default DB, always present
	    ],
	    crawler: {
	        proxy: false,
	        debug: 2,
	    },
	    scrapers: [
	        {
			//suite from the pjscrape library. check his docs.
	            suite: {
	                url: [
			        'http://spys.ru/free-proxy-list/DE/'
		        ],
	                //moreUrls: 'table table tr.spy1xx:eq(0) a',
	                ready: function() { return true; return _pjs$('table table tr.spy1xx[onmouseover]').length == 30 },
	                scraper: function() {
	                    return _pjs$('table table tr.spy1xx[onmouseover]').map(function() {
	                                    var item = this;
	                                    _pjs$('td:eq(0) script', item).remove();
	                                    
	                                    if ( ! _pjs$('td:eq(4)', item).is(':contains(ctbc)') ) {
	                                        return {
	                                            server: _pjs$('td:eq(0) font.spy14', item).text(),
	                                            type: _pjs$('td:eq(1)', item).text().toLowerCase(),
	                                            anon: _pjs$('td:eq(2)', item).text(),
	                                            country: _pjs$('td:eq(3)', item).text(),
	                                            date: _pjs$('td:eq(5)', item).text(),
	                                        }
	                                    }
	                    }).toArray();
	                },
	            },
			//Allow you to format your data before storing. One line per array row.
	            format: [
	                "$row->date = DateTime::createFromFormat(\"d-M-Y H:i\", $row->date)->getTimestamp()",
	                "$row->fail = 0",
	                "$row->agent = agent_random()"
	            ],
			//Database name, entity name, unique field name - to not be repeating yourself
	            store: {
	                database: 'config',
	                entity: 'proxy',
	                unique: 'server',
	            }
	        },
	    ],
	}

Then run from the command line
	php ./om-crawler.php proxy
The php core will look at the suites folder and run you configuration till end (moreUrls?)
Be carefull with the json as it still bugs sometimes.

That's it. You can check how to install in the docs folder.

TODO
-----

* Finish todo comments
* Make the docs
* Translate comments
* Later refactor
