const http = require('http');

class Server {

    constructor(cache, watcher, logger) {
        this._cache = cache;
        this._watcher = watcher;
        this._logger = logger || console;
        this._server = http.createServer(this._onRequet.bind(this));
    }

    listen(port, host) {
        port = port || 80;
        host = host || '0.0.0.0';

        this._server.listen(port, host, () => {
            this._logger.log(`[INIT] Server is running at http://${host}:${port}/`);
        });
    }

    _onRequet(request, response) {
        const {method} = request;
        response.setHeader('Content-Type', 'application/json');
        response.setHeader('X-Autoload-Watcher-Ready', this._watcher.isReady() ? '1' : '');

        this._readRequest(request, response, requestBody => {

            if (method === 'HEAD') {
                response.statusCode = 200;
            } else if (method === 'GET') {
                response.statusCode = 200;
                response.write(JSON.stringify(
                    this._cache.export()
                ));
            } else if (method === 'PUT') {
                response.statusCode = 201;
                this._cache.import(JSON.parse(requestBody));
            } else {
                response.statusCode = 400;
            }

            response.end();
        });
    }

    _readRequest(request, response, callback) {
        let body = '';
        request.on('error', (err) => {
            this._logger.error(err);
        }).on('data', (chunk) => {
            body += chunk;
        }).on('end', () => {

            response.on('error', (err) => {
                this._logger.error(err);
            });

            callback(body);
        });
    }
}

module.exports = Server;
