const path = require('path');
const chokidar = require('chokidar');

class Watcher {

    constructor(watchDir, includes, logger) {
        this._watchDir = watchDir;
        this._includes = includes;
        this._excludes = [
            (filePath, stats) => {
                if (stats) {
                    return !stats.isDirectory() && path.extname(filePath) !== '.php';
                }
                const extension = path.extname(filePath);
                return extension !== '' && extension !== '.php';
            }
        ];
        this._ready = false;
        this._watcher = null;
        this._logger = logger || console;
    }

    isReady() {
        return this._ready;
    }

    addExcludes(excludes) {
        this._excludes = this._excludes.concat(excludes);

        return this;
    }

    watch() {
        const timeStart = process.hrtime();
        this._logger.log('[INIT] Setting watchers for ' + this._includes.map(dir => `"${this._watchDir}${dir}"`).join(', '));
        process.platform !== 'win32' && process.platform !== 'darwin' && this._logger.log('[WARNING] This would take much time on Linux.');

        this._watcher = chokidar.watch(this._includes, {
            persistent: true,
            cwd: this._watchDir,
            ignored: this._excludes,
            disableGlobbing: false,
            ignoreInitial: true,
            followSymlinks: false, // turning this on makes setting watchers 600s (vs 300s) in Linux container.
            ignorePermissionErrors: true,
        });

        this._watcher.on('ready', () => {
            const timeEnd = process.hrtime(timeStart);
            this._logger.log('[READY] Watchers set in %ds', timeEnd[0]);
            this._ready = true;
        }).on('error', this._logger.error);

        return this;
    }

    subscribe(dispatcher) {
        this._watcher
            .on('add', dispatcher.onAdd)
            .on('addDir', dispatcher.onAddDir)
            .on('change', dispatcher.onChange)
            .on('unlink', dispatcher.onRemove)
            .on('unlinkDir', dispatcher.onRemoveDir)
        ;

        return this;
    }
}

module.exports = Watcher;
