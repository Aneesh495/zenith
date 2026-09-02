<?php

declare(strict_types=1);

namespace Zenith\Wasi;

/**
 * Standard WASI Preview 1 errno return codes.
 */
final class WasiErrno
{
    public const SUCCESS = 0;
    public const E2BIG = 1;
    public const EACCES = 2;
    public const EADDRINUSE = 3;
    public const EADDRNOTAVAIL = 4;
    public const EAFNOSUPPORT = 5;
    public const EAGAIN = 6;
    public const EALREADY = 7;
    public const EBADF = 8;
    public const EBADMSG = 9;
    public const EBUSY = 10;
    public const ECANCELED = 11;
    public const ECHILD = 12;
    public const ECONNABORTED = 13;
    public const ECONNREFUSED = 14;
    public const ECONNRESET = 15;
    public const EDEADLK = 16;
    public const EDESTADDRREQ = 17;
    public const EDOM = 18;
    public const EDQUOT = 19;
    public const EEXIST = 20;
    public const EFAULT = 21;
    public const EFBIG = 22;
    public const EHOSTUNREACH = 23;
    public const EIDRM = 24;
    public const EILSEQ = 25;
    public const EINPROGRESS = 26;
    public const EINTR = 27;
    public const EINVAL = 28;
    public const EIO = 29;
    public const EISCONN = 30;
    public const EISDIR = 31;
    public const ELOOP = 32;
    public const EMFILE = 33;
    public const EMLINK = 34;
    public const EMSGSIZE = 35;
    public const EMULTIHOP = 36;
    public const ENAMETOOLONG = 37;
    public const ENETDOWN = 38;
    public const ENETRESET = 39;
    public const ENETUNREACH = 40;
    public const ENFILE = 41;
    public const ENOBUFS = 42;
    public const ENODEV = 43;
    public const ENOENT = 44;
    public const ENOEXEC = 45;
    public const ENOLCK = 46;
    public const ENOLINK = 47;
    public const ENOMEM = 48;
    public const ENOMSG = 49;
    public const ENOPROTOOPT = 50;
    public const ENOSPC = 51;
    public const ENOSYS = 52;
    public const ENOTCONN = 53;
    public const ENOTDIR = 54;
    public const ENOTEMPTY = 55;
    public const ENOTRECOVERABLE = 56;
    public const ENOTSOCK = 57;
    public const ENOTSUP = 58;
    public const ENOTTY = 59;
    public const ENXIO = 60;
    public const EOVERFLOW = 61;
    public const EOWNERDEAD = 62;
    public const EPERM = 63;
    public const EPIPE = 64;
    public const EROFS = 69;
    public const ESPIPE = 70;
    public const ESRCH = 71;
    public const ETIMEDOUT = 73;
    public const ETXTBSY = 74;
    public const EXDEV = 75;
}
