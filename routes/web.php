<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\FilePermissionController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\SharedController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::get('/users', [AdminController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');
    Route::post('/users/{user}/update-group', [AdminController::class, 'updateGroup'])->name('admin.users.updateGroup');
    Route::patch('/admin/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');

    Route::get('/groups', [GroupController::class, 'index'])->name('admin.groups.index');
    Route::post('/groups', [GroupController::class, 'store'])->name('admin.groups.store');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('admin.groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('admin.groups.destroy');

    Route::get('/audit', [AuditController::class, 'index'])->name('admin.audit.index');

    Route::middleware('admin')->group(function () {
        Route::get('/import', [ImportController::class, 'index'])->name('admin.import.index');
        Route::post('/import/rescan', [ImportController::class, 'rescan'])->name('admin.import.rescan');

        Route::get('/backups', [BackupController::class, 'index'])->name('admin.backups.index');
        Route::post('/backups', [BackupController::class, 'run'])->name('admin.backups.run');
        Route::put('/backups/schedule', [BackupController::class, 'updateSchedule'])->name('admin.backups.schedule');
        Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('admin.backups.download');
        Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('admin.backups.restore');
        Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('admin.backups.destroy');
    });

    Route::get('/profile', [UserController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [UserController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [UserController::class, 'updateAvatar'])->name('profile.avatar');
    Route::get('/avatars/{user}', [UserController::class, 'avatar'])->name('users.avatar');
    Route::get('/', [FileController::class, 'index'])->name('files.index');
    Route::get('/photos', [PhotoController::class, 'index'])->name('photos.index');
    Route::post('/photos/upload', [PhotoController::class, 'upload'])
        ->middleware('throttle:60,1')->name('photos.upload');
    Route::post('/photos/albums', [PhotoController::class, 'storeAlbum'])->name('photos.albums.store');
    Route::delete('/photos/albums/{album}', [PhotoController::class, 'destroyAlbum'])->name('photos.albums.destroy');
    Route::post('/photos/albums/{album}/photos', [PhotoController::class, 'addToAlbum'])->name('photos.albums.add');
    Route::delete('/photos/albums/{album}/photos', [PhotoController::class, 'removeFromAlbum'])->name('photos.albums.remove');
    Route::post('/photos/albums/{album}/cover', [PhotoController::class, 'setCover'])->name('photos.albums.cover');
    Route::post('/photos/reorder', [PhotoController::class, 'reorder'])->name('photos.reorder');

    Route::get('/usage', [\App\Http\Controllers\StorageController::class, 'index'])->name('storage.index');

    Route::get('/break/crush', fn () => Inertia::render('Break/Crush'))->name('break.crush');

    Route::get('/break/dwg', [\App\Http\Controllers\DailyWordGameController::class, 'index'])->name('break.dwg');
    Route::post('/break/dwg/guess', [\App\Http\Controllers\DailyWordGameController::class, 'guess'])->name('break.dwg.guess');
    Route::get('/break/soduku', [\App\Http\Controllers\SodokuController::class, 'index'])->name('break.soduku');

        Route::get('/shared', [SharedController::class, 'index'])->name('shared.index');
    Route::get('/shared/{folder}', [SharedController::class, 'show'])->name('shared.show');

    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
    Route::delete('/trash/empty', [TrashController::class, 'empty'])->name('trash.empty');
    Route::post('/trash/batch/restore', [TrashController::class, 'batchRestore'])->name('trash.batch.restore');
    Route::post('/trash/{file}/restore', [TrashController::class, 'restore'])->withTrashed()->name('trash.restore');
    Route::delete('/trash/{file}', [TrashController::class, 'destroy'])->withTrashed()->name('trash.destroy');
    Route::post('/upload', [FileController::class, 'upload'])
        ->middleware('throttle:60,1')->name('files.upload');
    Route::post('/files/text', [FileController::class, 'createText'])->name('files.text');
    Route::get('/files/new/{type}', [FileController::class, 'newDocument'])->name('files.new');
    Route::post('/files/document', [FileController::class, 'storeDocument'])->name('files.document');
    Route::get('/download/{file}', [FileController::class, 'download'])->name('files.download');
    Route::get('/raw/{file}', [FileController::class, 'raw'])->name('files.raw');
    Route::get('/thumbnail/{file}', [FileController::class, 'thumbnail'])->name('files.thumbnail');
    Route::delete('/delete/{file}', [FileController::class, 'destroy'])->name('files.delete');
    Route::patch('/files/{file}/rename', [FileController::class, 'rename'])->name('files.rename');
    Route::post('/files/{file}/move', [FileController::class, 'move'])->whereNumber('file')->name('files.move');
    Route::post('/files/{file}/copy', [FileController::class, 'copy'])->name('files.copy');
    Route::put('/files/{file}/tags', [FileController::class, 'syncTags'])->name('files.tags');
    Route::post('/files/{file}/star', [FileController::class, 'star'])->name('files.star');
    Route::get('/files/{file}/edit', [FileController::class, 'edit'])->name('files.edit');
    Route::put('/files/{file}/content', [FileController::class, 'updateContent'])->name('files.content');
    Route::post('/files/{file}/content', [FileController::class, 'updateContent'])->name('files.content.post');
    Route::get('/files/{file}/permissions', [FilePermissionController::class, 'index'])->name('files.permissions.index');
    Route::post('/files/{file}/permissions', [FilePermissionController::class, 'store'])->name('files.permissions.store');
    Route::delete('/files/{file}/permissions/{permission}', [FilePermissionController::class, 'destroy'])->name('files.permissions.destroy');
    Route::get('/files/{file}/links', [ShareLinkController::class, 'index'])->name('files.links.index');
    Route::post('/files/{file}/links', [ShareLinkController::class, 'store'])->name('files.links.store');
    Route::delete('/files/{file}/links/{link}', [ShareLinkController::class, 'destroy'])->name('files.links.destroy');
    Route::get('/files/{file}/versions/{version}/download', [FileController::class, 'downloadVersion'])->name('files.versions.download');
    Route::post('/files/{file}/versions/{version}/restore', [FileController::class, 'restoreVersion'])->name('files.versions.restore');

    Route::post('/files/batch/move', [FileController::class, 'batchMove'])->name('files.batch.move');
    Route::post('/files/batch/delete', [FileController::class, 'batchDelete'])->name('files.batch.delete');
    Route::post('/files/batch/folder', [FileController::class, 'batchFolder'])->name('files.batch.folder');
    Route::post('/files/batch/rename', [FileController::class, 'batchRename'])->name('files.batch.rename');

    Route::post('/saved-searches', [\App\Http\Controllers\SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::delete('/saved-searches/{savedSearch}', [\App\Http\Controllers\SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');

    Route::post('/folders', [FileController::class, 'createFolder'])->name('folders.create');
    Route::get('/folders/{folder}', [FileController::class, 'viewFolder'])->name('folders.view');

    // Secrets vault. Reveal/generate are rate-limited; reveal also re-checks the
    // user's password (in the controller) and is audited.
    Route::get('/vault', [\App\Http\Controllers\VaultController::class, 'index'])->name('vault.index');
    Route::post('/vault', [\App\Http\Controllers\VaultController::class, 'store'])->name('vault.store');
    Route::put('/vault/{vaultItem}', [\App\Http\Controllers\VaultController::class, 'update'])->whereNumber('vaultItem')->name('vault.update');
    Route::delete('/vault/{vaultItem}', [\App\Http\Controllers\VaultController::class, 'destroy'])->whereNumber('vaultItem')->name('vault.destroy');
    Route::post('/vault/{vaultItem}/reveal', [\App\Http\Controllers\VaultController::class, 'reveal'])
        ->whereNumber('vaultItem')->middleware('throttle:20,1')->name('vault.reveal');
    Route::get('/vault/generate', [\App\Http\Controllers\VaultController::class, 'generate'])
        ->middleware('throttle:60,1')->name('vault.generate');
    Route::post('/vault/import', [\App\Http\Controllers\VaultController::class, 'import'])
        ->middleware('throttle:10,1')->name('vault.import');

    // Starred — cross-app view of starred notes, bookmarks, and files.
    Route::get('/starred', [\App\Http\Controllers\StarredController::class, 'index'])->name('starred.index');

    // Staff directory.
    Route::get('/directory', [\App\Http\Controllers\DirectoryController::class, 'index'])->name('directory.index');
    Route::get('/directory/{user}', [\App\Http\Controllers\DirectoryController::class, 'show'])->whereNumber('user')->name('directory.show');

    // Bookmarks — the internal-links launchpad.
    Route::get('/bookmarks', [\App\Http\Controllers\BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/bookmarks', [\App\Http\Controllers\BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::put('/bookmarks/{bookmark}', [\App\Http\Controllers\BookmarkController::class, 'update'])->name('bookmarks.update');
    Route::delete('/bookmarks/{bookmark}', [\App\Http\Controllers\BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::get('/bookmarks/{bookmark}/go', [\App\Http\Controllers\BookmarkController::class, 'go'])->whereNumber('bookmark')->name('bookmarks.go');
    Route::post('/bookmarks/{bookmark}/star', [\App\Http\Controllers\BookmarkController::class, 'star'])->whereNumber('bookmark')->name('bookmarks.star');
    Route::get('/bookmarks/{bookmark}/icon', [\App\Http\Controllers\BookmarkController::class, 'icon'])->whereNumber('bookmark')->name('bookmarks.icon');
    Route::get('/bookmarks/{bookmark}/screenshot', [\App\Http\Controllers\BookmarkController::class, 'screenshot'])->whereNumber('bookmark')->name('bookmarks.screenshot');
    Route::post('/bookmarks/import', [\App\Http\Controllers\BookmarkController::class, 'import'])
        ->middleware('throttle:10,1')->name('bookmarks.import');
    Route::post('/bookmarks/dedup', [\App\Http\Controllers\BookmarkController::class, 'dedup'])->name('bookmarks.dedup');
    Route::post('/bookmarks/prune', [\App\Http\Controllers\BookmarkController::class, 'prune'])->name('bookmarks.prune');
    Route::post('/bookmarks/validate', [\App\Http\Controllers\BookmarkController::class, 'validateAll'])
        ->middleware('throttle:6,1')->name('bookmarks.validate');
    Route::post('/bookmarks/hydrate', [\App\Http\Controllers\BookmarkController::class, 'hydrate'])
        ->middleware('throttle:6,1')->name('bookmarks.hydrate');

    // Notes — a note-centric surface over markdown files.
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::post('/notes/folders', [NoteController::class, 'createFolder'])->name('notes.folders.create');
    Route::put('/notes/{file}/autosave', [NoteController::class, 'autosave'])
        ->whereNumber('file')->name('notes.autosave');
    Route::put('/notes/{file}/rename', [NoteController::class, 'rename'])
        ->whereNumber('file')->name('notes.rename');
    Route::get('/notes/{file}/content', [NoteController::class, 'content'])
        ->whereNumber('file')->name('notes.content');
    Route::get('/notes/{file}/backlinks', [NoteController::class, 'backlinks'])
        ->whereNumber('file')->name('notes.backlinks');
    Route::get('/notes/search/notes', [NoteController::class, 'searchNotes'])
        ->middleware('throttle:120,1')->name('notes.search.notes');
    Route::get('/notes/search/users', [NoteController::class, 'searchUsers'])
        ->middleware('throttle:120,1')->name('notes.search.users');
});

// Public share links (no authentication). Rate-limited: the unlock endpoint
// is the brute-force surface for password-protected links.
Route::get('/s/{token}', [PublicShareController::class, 'show'])->name('shares.public.show');
Route::post('/s/{token}/unlock', [PublicShareController::class, 'unlock'])
    ->middleware('throttle:5,1')->name('shares.public.unlock');
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/s/{token}/raw', [PublicShareController::class, 'raw'])->name('shares.public.raw');
    Route::get('/s/{token}/download', [PublicShareController::class, 'download'])->name('shares.public.download');
});

Auth::routes(['verify' => false]);

// Legacy dashboard route now lands on the file manager.
Route::get('/home', fn () => redirect()->route('files.index'))->name('home');
