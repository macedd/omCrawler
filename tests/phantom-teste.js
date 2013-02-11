var page = require('webpage').create(),
    t = Date.now(), address;

//page request settings
console.log('The default user agent is ' + page.settings.userAgent);
page.settings.userAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:13.0) Gecko/20100101 Firefox/13.0';

//download files
page.settings.loadImages = false;

//evaluated page console
//page.onConsoleMessage = function (msg) { console.log('Page title is ' + msg); };
//netsniff [make optional]
//page.onResourceRequested = function (request) { console.log('Request ' + JSON.stringify(request, undefined, 4)); };
//page.onResourceReceived = function (response) { console.log('Receive ' + JSON.stringify(response, undefined, 4)); };

if (phantom.args.length < 1 || phantom.args.length > 3) {
    console.log('Usage: teste.js URL [filename');
    phantom.exit();
} else {
    //receive comand-line arguments
    address = phantom.args[0];

    //open a page
    page.open(address, function (status) {
        //assync access to the page

        if (status !== 'success') {
            console.log('Unable to access network');
        } else {
            //evaluete code
            page.includeJs("http://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.min.js", function() {
                var title = page.evaluate(function () {
                    return $('title').text();
                });
                console.log('Page title is ' + title);
                
                var fs = require("fs"); 
                fs.write("/dev/stdout", "Vamos ao print!\n", "w");

                //print screen
                if (phantom.args[1]) {
                    output = phantom.args[1];
                    page.viewportSize = { width: 600, height: 600 };

                    window.setTimeout(function () {
                        page.render(output);

                        t = Date.now() - t;
                        console.log('Loading time ' + (t*0.001) + ' secs');

                        phantom.exit();
                    }, 200);

                } else {
                    t = Date.now() - t;
                    console.log('Loading time ' + (t*0.001) + ' secs');

                    phantom.exit();
                }
            });
        }
    });
}

