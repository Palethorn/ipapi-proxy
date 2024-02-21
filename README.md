# ABOUT
Proxy for ipapi.is. If a cache record is found then the proxy skips the request to their API and serves a cached 
record, otherwise it performs n external lookup request. Caching is important because the records don't change very 
frequently, while ipapi.is limits the number of lookups. Using this proxy you can perform multiple requests from 
multiple nodes without too much worry of reaching the limit too fast. You will probably still reach the limit for a high 
traffic site.

# INSTRUCTIONS
- Clone the repository
- Execute > php composer install
- Edit copy config.php.template to config.php and set your directories and token

# RESPONSES

## Request limit exceeded
Happens when number of external requests towards ipapi.is is reached. This means that proxy did not find the record in 
cache, but the daily request limit is equal to ipapi_request_limit in config.php

```
{
    "message": "ipapi request limit exceeded"
}
```

## Invalid IP address
When the IP format is not correct.

```
{
    "message": "Invalid IP address"
}
```

## ipapi.is errors
Responses with status 200 are returned transparently, but sometimes some other status codes can occur. You can expect
this kind of response in such cases. 

HTTP Status Code: 503
```
{
    "message": "ipapi error: <underlying_error_message>"
}
```