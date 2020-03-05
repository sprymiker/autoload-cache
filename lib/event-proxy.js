const net = require('net');

class EventProxy {

    constructor(port, host, logger) {
        this._host = host;
        this._port = port;
        this._logger = logger || console;
        this.onAdd = this.onChange = this.onRemove = this._send.bind(this);
        this.onAddDir = this.onRemoveDir = () => {
        };
    }

    _send(filePath) {
        this._connect((client) => {
            client.write(filePath + "\n");
        });
    }

    _connect(callback) {

        if (this._client) {
            callback(this._client);
            return;
        }

        if (this._lastConnect && process.hrtime(this._lastConnect)[0] < 3) {
            return;
        }

        this._client = new net.Socket();
        this._logger.info(`[I] Connecting to change proxy: ${this._host}:${this._port}`);

        this._client.connect(this._port, this._host, () => {
            this._logger.info('[I] Connected to change proxy.');
            this._lastConnect = process.hrtime();
            callback(this._client);
        });

        this._client.on('close', () => {
            this._logger.info('[I] Disconnected from change proxy.');
            this._client = null
        });
    }
}

module.exports = EventProxy;
