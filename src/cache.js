const path = require('path');
const fs = require('fs');

class Cache {

    constructor(logger) {
        this._logger = logger || console;
        this._classMap = {};
        this._fileMap = {};
        this._cache = {};
        this._changed = false;
    }

    _walkForCache(obj, path) {
        for (let key in obj) {
            if (obj.hasOwnProperty(key)) {

                if (typeof obj[key] === 'object') {
                    this._walkForCache(obj[key], (path ? path + '/' : '') + key);
                    continue;
                }
                this._cache[key] = path;
            }
        }
    }

    export() {
        if (this._changed) {
            this._logger.info(`[I] Changes detected. Flushing cache.`);
            this._cache = {};

            for (let key in this._classMap) {
                if (this._classMap.hasOwnProperty(key)) {
                    Object.assign(this._cache, this._classMap[key]);
                }
            }

            this._walkForCache(this._fileMap, '');
            this._changed = false;
        }

        return this._cache;
    }

    import(payload) {
        let counter = 0;
        for (let key in payload) {
            if (payload.hasOwnProperty(key)) {
                counter++;
                const value = payload[key];
                if (typeof value === 'string' && value.length > 0) {
                    this._addFileMap(value, key);
                    continue;
                }

                this._addClassMap(key);
            }
        }
        this._logger.info(`[I] ${counter} record(s) added.`);
    }

    _addClassMap(key) {
        const baseName = key.split('\\').pop();
        if (typeof this._classMap[baseName] === 'undefined') {
            this._classMap[baseName] = {};
        }
        this._classMap[baseName][key] = false;
        this._changed = true;
    }

    _addFileMap(value, key) {

        if (!fs.existsSync('./' + value)) {
            // Prevent file system caches to impact on cache map.
            return;
        }

        let pointer = this._fileMap;
        value.replace(/^\//, '').split('/').forEach((segment, index, array) => {
            if (typeof pointer[segment] === 'undefined') {
                pointer[segment] = {};
            }
            pointer = pointer[segment];
        });
        pointer[key] = true;
        this._changed = true;
    }

    isFileProcessable(filePath) {
        return path.extname(filePath) === '.php'
    }

    clearClassMap(filePath) {
        const className = path.basename(filePath, path.extname(filePath));
        this._classMap.hasOwnProperty(className) && (this._changed = true);
        this._logger.info(`[I] Removing all classes "${className}"`);
        delete this._classMap[className];
    }

    clearFileMap(filePath) {
        const objectPath = String(filePath).replace(/^\//, '').replace(/\//g, '"]["');
        this._logger.info(`[I] Removing branch Map["${objectPath}"]`);
        const $delete = `delete this._fileMap["${objectPath}"];`;
        try {
            eval($delete);
            this._changed = true
        } catch (e) {
            // do nothing
        }
    }
}

class Dispatcher {

    constructor(cache, logger) {
        this._cache = cache;
        this._logger = logger || console;
        this.onAdd = this.onAdd.bind(this);
        this.onAddDir = this.onAddDir.bind(this);
        this.onChange = this.onChange.bind(this);
        this.onRemove = this.onRemove.bind(this);
        this.onRemoveDir = this.onRemoveDir.bind(this);
    }

    onAdd(filePath) {
        if (!this._cache.isFileProcessable(filePath)) {
            return;
        }
        this._logger.log(`[+] ${filePath}`);
        this._cache.clearFileMap(filePath);
        this._cache.clearClassMap(filePath);
    }

    onChange(filePath) {
        if (!this._cache.isFileProcessable(filePath)) {
            return;
        }
        this._logger.log(`[*] ${filePath}`);
        this._cache.clearFileMap(filePath);
        this._cache.clearClassMap(filePath);
    }

    onRemove(filePath) {
        if (!this._cache.isFileProcessable(filePath)) {
            return;
        }
        this._logger.log(`[-] ${filePath}`);
        this._cache.clearFileMap(filePath);
    }

    onAddDir(filePath) {
        this._logger.log(`[+] ${filePath}`);
    }

    onRemoveDir(filePath) {
        this._logger.log(`[-] ${filePath}`);
        this._cache.clearFileMap(filePath);
    }
}

module.exports.Cache = Cache;
module.exports.Dispatcher = Dispatcher;
