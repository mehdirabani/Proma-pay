# Chat Unread Badge System

Unread state is calculated per user and channel using the last-read message cursor. Opening one channel updates only that channel. Management, operator, lawyer and customer shells use the same unread source, preventing cross-role count drift.

The cursor table has a unique `(channel_id, user_id)` key so repeated open/read operations remain idempotent.
