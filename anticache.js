const yargs = require('yargs');
const fs = require('fs');
const net = require('net');
const readline = require('readline');

// - COMMAND -----------------

const argv = yargs
    .option('port', {
        alias: 'p',
        description: 'Listens to port',
        type: 'number',
        default: 8998,
    })
    .option('ip', {
        alias: 'i',
        description: 'IP to listen',
        type: 'string',
        default: '0.0.0.0',
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

// - START -----------------

const verbose = argv.verbose;

const server = net.createServer(function (socket) {

    readline.createInterface({
        input: socket,
        output: socket,
    }).on('line', function (line) {
        const filePath = './' + line.trim();
        try {
            // Opening file with write access drops NFS cache
            fs.open(filePath, 'r+', (error, fileDescriptor) => {
                verbose && console.info(`[` + (error ? '-' : '+') + `] ${filePath}`);
                fileDescriptor && fs.close(fileDescriptor, () => {
                    // do nothing
                });
            });
        } catch (e) {
            verbose && console.info(`[-] ${filePath}`);
        }
    });
});

server.listen(argv.port, argv.ip);
