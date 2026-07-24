<?php

namespace App\Workflow;

/**
 * The strict, ordered set of activity kinds a v1 workflow can contain.
 *
 * v1 ships three kinds:
 *   - CONDITION (order 20): the rule's conditions_json
 *   - ACTION    (order 40): wraps a registered ActionHandler
 *   - END       (order 90): terminal
 *
 * v1 workflows are always linear: CONDITION → ACTION × N → END.
 *
 * The trigger event lives on the rule itself (`trigger_event`); it is
 * not a workflow activity. The dispatcher matches the trigger; the
 * workflow only ever runs from CONDITION forward.
 */
enum ActivityType: string
{
    case CONDITION = 'condition';
    case ACTION = 'action';
    case END = 'end';

    public function order(): int
    {
        return match ($this) {
            self::CONDITION => 20,
            self::ACTION => 40,
            self::END => 90,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::END;
    }

    public function isExecutable(): bool
    {
        return $this === self::ACTION || $this === self::CONDITION;
    }
}
