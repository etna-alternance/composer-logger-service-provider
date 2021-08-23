# composer-logger-service-provider

ETNA logger service provider

## Configuration

The logger is configured via environment variables:

| Variable              | Exemple                                          | Description                                                                |
|-----------------------|--------------------------------------------------|----------------------------------------------------------------------------|
| `ETNA_LOGLEVEL`       | `debug`, `info` , `warning`, `error`, `critical` | log level. default to `debug` if running on debug, `info` otherwise.       |
| `ETNA_LOGFILE`        | `/var/log/myapp.log`                             | path to a file, where to output logs. Using it disables logging to stderr. |
| `ETNA_SYSLOG_ENABLED` | `true`, `yes`, `1`, `false`, `no`, `0`           | Enable syslog logging                                                      |

