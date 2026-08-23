<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditEventType: string
{
    case Created = 'CREATED';
    case Updated = 'UPDATED';
    case Deleted = 'DELETED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Requested = 'REQUESTED';
    case Verified = 'VERIFIED';
    case Resolved = 'RESOLVED';
    case Assigned = 'ASSIGNED';
    case Decided = 'DECIDED';
    case Changed = 'CHANGED';
    case Matched = 'MATCHED';
    case Canceled = 'CANCELED';
    case Confirmed = 'CONFIRMED';
    case Completed = 'COMPLETED';
    case Generated = 'GENERATED';
    case Reprocessed = 'REPROCESSED';
    case Closed = 'CLOSED';
    case Submitted = 'SUBMITTED';
    case Recorded = 'RECORDED';
    case Reversed = 'REVERSED';
    case Disbursed = 'DISBURSED';
    case Sent = 'SENT';
    case Resent = 'RESENT';
    case Failed = 'FAILED';
    case PreAuthorized = 'PRE_AUTHORIZED';
    case Login = 'LOGIN';
    case Logout = 'LOGOUT';
}
