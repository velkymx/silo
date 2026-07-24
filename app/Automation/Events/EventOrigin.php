<?php

namespace App\Automation\Events;

/**
 * Where an event came from. Lets future rules scope themselves (e.g.
 * "only fire for events originated on the web") and lets the logs
 * filter by source.
 */
enum EventOrigin: string
{
    case WEB = 'web';
    case API = 'api';
    case SCHEDULER = 'scheduler';
    case AUTOMATION = 'automation';
    case MCP = 'mcp';
    case CLI = 'cli';
    case REPLAY = 'replay';
}
