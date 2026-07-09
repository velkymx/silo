<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Automation\RuleController as AutomationRuleController;
use App\Http\Controllers\Automation\RuleExecutionController as AutomationRuleExecutionController;
use App\Http\Controllers\Automation\TemplateController as AutomationTemplateController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\DailyWordGameController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FilePermissionController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\Rss\FeedController as RssFeedController;
use App\Http\Controllers\Rss\ItemController as RssItemController;
use App\Http\Controllers\Rss\NotificationController as RssNotificationController;
use App\Http\Controllers\Rss\OpmlController as RssOpmlController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SharedController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\SodokuController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// HI-06: Constrain all numeric model-binding parameters globally so non-integer
// segments return 404 instead of a model-not-found exception (consistent behaviour).
foreach (['file', 'folder', 'user', 'group', 'backup', 'album', 'permission',
    'link', 'version', 'bookmark', 'savedSearch', 'vaultItem',
    'feed', 'item', 'rule', 'ruleExecution', 'notification'] as $param) {
    Route::pattern($param, '[0-9]+');
}

Route::middleware(['auth'])->group(function () {
    Route::middleware('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'index'])->name('admin.users.index');
        Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');
        Route::post('/users/{user}/update-group', [AdminController::class, 'updateGroup'])->name('admin.users.updateGroup');
        Route::patch('/admin/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');

        Route::get('/groups', [GroupController::class, 'index'])->name('admin.groups.index');
        Route::post('/groups', [GroupController::class, 'store'])->name('admin.groups.store');
        Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('admin.groups.update');
        Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('admin.groups.destroy');

        Route::get('/audit', [AuditController::class, 'index'])->name('admin.audit.index');

        Route::get('/import', [ImportController::class, 'index'])->name('admin.import.index');
        Route::post('/import/rescan', [ImportController::class, 'rescan'])->name('admin.import.rescan');

        Route::get('/backups', [BackupController::class, 'index'])->name('admin.backups.index');
        Route::post('/backups', [BackupController::class, 'run'])->name('admin.backups.run');
        Route::put('/backups/schedule', [BackupController::class, 'updateSchedule'])->name('admin.backups.schedule');
        Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('admin.backups.download');
        Route::post('/backups/{backup}/verify', [BackupController::class, 'verify'])->name('admin.backups.verify');
        Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('admin.backups.restore');
        Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('admin.backups.destroy');
    });

    Route::get('/profile', [UserController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [UserController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [UserController::class, 'updateAvatar'])->name('profile.avatar');
    Route::get('/avatars/{user}', [UserController::class, 'avatar'])->name('users.avatar');
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/search/quick', [SearchController::class, 'quick'])
        ->middleware('throttle:60,1')->name('search.quick');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/about', fn () => Inertia::render('About', [
        'version' => config('silo.version'),
        'developer' => config('silo.developer'),
    ]))->name('about');
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

    Route::get('/usage', [StorageController::class, 'index'])->name('storage.index');

    Route::get('/break/crush', fn () => Inertia::render('Break/Crush'))->name('break.crush');

    Route::get('/break/dwg', [DailyWordGameController::class, 'index'])->name('break.dwg');
    Route::post('/break/dwg/guess', [DailyWordGameController::class, 'guess'])->name('break.dwg.guess');
    Route::get('/break/sodoku', [SodokuController::class, 'index'])->name('break.sodoku');

    Route::get('/recent', [FileController::class, 'index'])->defaults('section', 'recent')->name('files.recent');

    Route::get('/shared', [FileController::class, 'index'])->defaults('section', 'shared')->name('shared.index');
    Route::get('/shared/{folder}', [SharedController::class, 'show'])->name('shared.show');

    Route::get('/trash', [FileController::class, 'index'])->defaults('section', 'trash')->name('trash.index');
    Route::delete('/trash/empty', [TrashController::class, 'empty'])->name('trash.empty');
    Route::post('/trash/batch/restore', [TrashController::class, 'batchRestore'])->name('trash.batch.restore');
    Route::post('/trash/{file}/restore', [TrashController::class, 'restore'])->withTrashed()->name('trash.restore');
    Route::delete('/trash/{file}', [TrashController::class, 'destroy'])->withTrashed()->name('trash.destroy');
    Route::post('/upload', [FileController::class, 'upload'])
        ->middleware('throttle:60,1')->name('files.upload');
    Route::get('/files/tree', [FileController::class, 'tree'])->name('files.tree');
    Route::post('/files/text', [FileController::class, 'createText'])
        ->middleware('throttle:30,1')->name('files.text');
    Route::get('/files/new/{type}', [FileController::class, 'newDocument'])->name('files.new');
    Route::post('/files/document', [FileController::class, 'storeDocument'])
        ->middleware('throttle:30,1')->name('files.document');
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
    Route::get('/files/{file}/versions/{version}/download', [VersionController::class, 'download'])->name('files.versions.download');
    Route::post('/files/{file}/versions/{version}/restore', [VersionController::class, 'restore'])->name('files.versions.restore');

    Route::post('/files/batch/move', [BatchController::class, 'move'])->name('files.batch.move');
    Route::post('/files/batch/delete', [BatchController::class, 'delete'])->name('files.batch.delete');
    Route::post('/files/batch/folder', [BatchController::class, 'folder'])->name('files.batch.folder');
    Route::post('/files/batch/rename', [BatchController::class, 'rename'])->name('files.batch.rename');

    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::post('/saved-searches/{savedSearch}/favorite', [SavedSearchController::class, 'toggleFavorite'])->name('saved-searches.favorite');
    Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');

    Route::post('/folders', [FolderController::class, 'store'])->name('folders.create');
    // ME-03: lazy folder lookup for the move/copy picker. Returns matching
    // folders owned by the current user; supports parent + search filters.
    Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
    Route::get('/folders/{folder}', [FolderController::class, 'show'])->name('folders.view');

    // Secrets vault. Reveal/generate are rate-limited; reveal also re-checks the
    // user's password (in the controller) and is audited.
    Route::get('/vault', [VaultController::class, 'index'])->name('vault.index');
    Route::post('/vault', [VaultController::class, 'store'])->name('vault.store');
    Route::put('/vault/{vaultItem}', [VaultController::class, 'update'])->whereNumber('vaultItem')->name('vault.update');
    Route::delete('/vault/{vaultItem}', [VaultController::class, 'destroy'])->whereNumber('vaultItem')->name('vault.destroy');
    Route::post('/vault/{vaultItem}/reveal', [VaultController::class, 'reveal'])
        ->whereNumber('vaultItem')->middleware('throttle:20,1')->name('vault.reveal');
    Route::get('/vault/generate', [VaultController::class, 'generate'])
        ->middleware('throttle:60,1')->name('vault.generate');
    Route::post('/vault/import', [VaultController::class, 'import'])
        ->middleware('throttle:10,1')->name('vault.import');

    // Starred — the files shell scoped to starred files and folders.
    Route::get('/starred', [FileController::class, 'index'])->defaults('section', 'starred')->name('starred.index');

    // Staff directory.
    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::get('/directory/{user}', [DirectoryController::class, 'show'])->whereNumber('user')->name('directory.show');

    // Bookmarks — the internal-links launchpad.
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::put('/bookmarks/{bookmark}', [BookmarkController::class, 'update'])->name('bookmarks.update');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::get('/bookmarks/{bookmark}/go', [BookmarkController::class, 'go'])->whereNumber('bookmark')->name('bookmarks.go');
    Route::post('/bookmarks/{bookmark}/star', [BookmarkController::class, 'star'])->whereNumber('bookmark')->name('bookmarks.star');
    Route::post('/bookmarks/{bookmark}/subscribe', [BookmarkController::class, 'subscribeFeed'])->whereNumber('bookmark')->name('bookmarks.subscribe');
    Route::get('/bookmarks/{bookmark}/icon', [BookmarkController::class, 'icon'])->whereNumber('bookmark')->name('bookmarks.icon');
    Route::get('/bookmarks/{bookmark}/screenshot', [BookmarkController::class, 'screenshot'])->whereNumber('bookmark')->name('bookmarks.screenshot');
    Route::post('/bookmarks/import', [BookmarkController::class, 'import'])
        ->middleware('throttle:10,1')->name('bookmarks.import');
    Route::post('/bookmarks/dedup', [BookmarkController::class, 'dedup'])->name('bookmarks.dedup');
    Route::post('/bookmarks/prune', [BookmarkController::class, 'prune'])->name('bookmarks.prune');
    Route::post('/bookmarks/validate', [BookmarkController::class, 'validateAll'])
        ->middleware('throttle:6,1')->name('bookmarks.validate');
    Route::post('/bookmarks/hydrate', [BookmarkController::class, 'hydrate'])
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

    // RSS reader — the first event-driven subsystem. Feeds, items, rules,
    // execution logs, and notification center all hang off the same /rss tree.
    Route::prefix('rss')->name('rss.')->group(function () {
        Route::get('/', [RssFeedController::class, 'index'])->name('index');
        Route::post('/feeds', [RssFeedController::class, 'store'])->name('feeds.store');
        Route::patch('/feeds/{feed}', [RssFeedController::class, 'update'])->name('feeds.update');
        Route::delete('/feeds/{feed}', [RssFeedController::class, 'destroy'])->name('feeds.destroy');
        Route::post('/feeds/{feed}/refresh', [RssFeedController::class, 'refresh'])
            ->middleware('throttle:12,1')->name('feeds.refresh');
        Route::post('/feeds/refresh-all', [RssFeedController::class, 'refreshAll'])
            ->middleware('throttle:4,1')->name('feeds.refreshAll');
        Route::post('/feeds/{feed}/mute', [RssFeedController::class, 'mute'])->name('feeds.mute');
        Route::post('/feeds/{feed}/unmute', [RssFeedController::class, 'unmute'])->name('feeds.unmute');
        Route::post('/discover', [RssFeedController::class, 'discover'])
            ->middleware('throttle:6,1')->name('feeds.discover');
        Route::get('/stats', [RssFeedController::class, 'stats'])->name('stats');
        Route::post('/opml/import', [RssOpmlController::class, 'store'])->name('opml.import');
        Route::post('/opml/import-url', [RssOpmlController::class, 'importFromUrl'])
            ->middleware('throttle:6,1')->name('opml.importUrl');
        Route::get('/opml/export', [RssOpmlController::class, 'export'])->name('opml.export');

        Route::get('/items/{item}', [RssItemController::class, 'show'])->name('items.show');
        Route::get('/feeds/{feed}/favicon', [\App\Http\Controllers\Rss\FeedController::class, 'favicon'])->name('feeds.favicon');
        Route::post('/items/{item}/read', [RssItemController::class, 'markRead'])->name('items.read');
        Route::post('/items/{item}/unread', [RssItemController::class, 'markUnread'])->name('items.unread');
        Route::post('/items/{item}/star', [RssItemController::class, 'toggleStar'])->name('items.star');
        Route::post('/items/mark-all-read', [RssItemController::class, 'markAllRead'])->name('items.markAllRead');

        Route::get('/rules', [AutomationRuleController::class, 'index'])->name('rules.index');
        Route::post('/rules', [AutomationRuleController::class, 'store'])->name('rules.store');
        Route::patch('/rules/{rule}', [AutomationRuleController::class, 'update'])->name('rules.update');
        Route::delete('/rules/{rule}', [AutomationRuleController::class, 'destroy'])->name('rules.destroy');
        Route::post('/rules/{rule}/toggle', [AutomationRuleController::class, 'toggle'])->name('rules.toggle');

        Route::get('/rules/logs', [AutomationRuleExecutionController::class, 'index'])->name('rules.logs');
        Route::post('/rules/logs/{ruleExecution}/replay', [AutomationRuleExecutionController::class, 'replay'])->name('rules.logs.replay');

        Route::get('/rules/templates', [AutomationTemplateController::class, 'index'])->name('rules.templates');
        Route::post('/rules/templates/{template}/apply', [AutomationTemplateController::class, 'apply'])->name('rules.templates.apply');

        Route::get('/notifications', [RssNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [RssNotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [RssNotificationController::class, 'markAllRead'])->name('notifications.readAll');
    });
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
