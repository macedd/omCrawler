phantom.injectJs('../pjscrape/client/jquery.js');

var pjs = function() {
    //todo: convert obj to a array
    var objToString = function(obj) {
        for (prop in obj) {
            if ( obj[prop].constructor==Object )
                obj[prop] = objToString( obj[prop] );
            else
                obj[prop] = obj[prop].toString();
        }
        
        return obj;
    }
    
    var suites = [];
    var config = [];
    
    return {
        addSuite: function( ) {
            suites = Array.prototype.concat.apply(suites, arguments);
        },
        config: function(key, val) {
            if (!key) {
                return config;
            } else if (typeof key == 'object') {
                $.extend(config, key);
            } else if (val) {
                config[key] = val;
            }
        },
        toJSON: function() {
            for (s in suites) {
                suites[s] = objToString( suites[s] );
            }
            
            return JSON.stringify( [suites, config] );
        }
    }
}();

phantom.args.forEach(function(configFile) {
        try {
            eval(configFile);
        } catch(e) {
            // load the config file(s)
            if (!phantom.injectJs(configFile)) {
                fail('Config file not found or wrong input: ' + configFile);
            }
        }
});

console.log(pjs.toJSON());

phantom.exit();
