db.results.find({}, { meta: 1 }).sort({ 'meta.request_ts': -1 }).limit(8).forEach(function (d) {
    print(d.meta.request_ts, '| SERVER_NAME=' + d.meta.SERVER.SERVER_NAME, '| HTTP_HOST=' + d.meta.SERVER.HTTP_HOST, '| url=' + d.meta.url);
});