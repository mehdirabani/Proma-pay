# Referral 404 root cause

Tracking ID `ce182aed3add5d549d8c56b0` is not present in locally available protected logs. The local manifest registers the administrator route and separate customer routes. The core only registers plugin routes while the registry status is active; an old 1.2.6/1.2.9 registry or incomplete activation therefore falls through to the branded 404. The deployed lifecycle state remains BLOCKED until production route/registry logs are supplied. Version 1.2.12 retains the route list and legacy nested ZIP structure.
