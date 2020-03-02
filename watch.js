const yargs = require('yargs');

const {Cache, Dispatcher} = require('./src/cache');
const Watcher = require('./src/watcher');
const ChangeProxy = require('./src/change-proxy.js');
const Server = require('./src/server');

try {
    (process.platform === 'darwin') && require('fsevents');
} catch (error) {
    // https://github.com/fsevents/fsevents/issues/270
    // v2.x is supported in ^v8.16 , ^v10.4 , v11.0+
    console.warn('[WARNING] It is recommended to update NodeJS to allow using MacOS `fsevents`.');
}

// - COMMAND -----------------

const argv = yargs
    .option('port', {
        alias: 'p',
        description: 'Listens to port',
        type: 'number',
        default: 8999,
    })
    .option('ip', {
        alias: 'i',
        description: 'IP to listen',
        type: 'string',
        default: '0.0.0.0',
    })
    .option('include', {
        alias: 'e',
        description: 'List of folders to include',
        type: 'array',
        default: ['src', 'tests', 'vendor'],
    })
    .option('exclude', {
        alias: 'x',
        description: 'List of glob patterns to exclude',
        type: 'array',
    })
    .option('proxy-port', {
        description: 'Change proxy port',
        type: 'string',
    })
    .option('proxy-host', {
        description: 'Change proxy host',
        type: 'string',
    })
    .option('verbose', {
        alias: 'v',
        description: 'Verbose mode',
        type: 'boolean',
    })
    .strict()
    .wrap(Math.min(yargs.terminalWidth(), 100))
    .help()
    .alias('help', 'h')
    .version(false)
    .argv;

// - PREPARE -----------------

const watchDir = './';
const includes = argv.include.map(dir => dir.replace(/^\//, '') + '/');

const logger = {...console, info: argv.verbose ? console.info : () => ({})};
const cache = new Cache(logger);
const dispatcher = new Dispatcher(cache, logger);
const watcher = new Watcher(watchDir, includes, logger);
const changeProxy = new ChangeProxy(argv.proxyPort, argv.proxyHost, logger);
const server = new Server(cache, watcher, logger);

watcher
    .addExcludes(['vendor/bin/*', 'vendor/composer/*.php', 'vendor/autoload.php', '**/.git', '**/.git/**', '**/node_modules', '**/node_modules/**'])
    .addExcludes(argv.exclude);

// - START -----------------

server.listen(argv.port, argv.ip);
watcher.watch()
    .subscribe(dispatcher)
    .subscribe(changeProxy);
