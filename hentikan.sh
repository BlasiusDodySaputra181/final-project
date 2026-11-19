#!/bin/sh
ROOT=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
exec sh "$ROOT/laravel-assets/scripts/runtime.sh" stop
