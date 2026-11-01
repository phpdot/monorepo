<?php

declare(strict_types=1);

/**
 * SMTP session states per RFC 5321.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\Enum;

/**
 * RFC 5321 Section 3: SMTP session states.
 *
 * Connected → Greeted → Authenticated → Transaction → Data → Closing
 */
enum SessionState: string
{
    /** Connection established, awaiting EHLO/HELO. */
    case Connected = 'CONNECTED';

    /** EHLO/HELO received, pre-auth. */
    case Greeted = 'GREETED';

    /** TLS negotiated, ready for AUTH or mail. */
    case Secured = 'SECURED';

    /** Authenticated via AUTH command. */
    case Authenticated = 'AUTHENTICATED';

    /** MAIL FROM accepted, in mail transaction. */
    case Transaction = 'TRANSACTION';

    /** DATA command accepted, receiving message body. */
    case Data = 'DATA';

    /** QUIT received, connection closing. */
    case Closing = 'CLOSING';
}
