print('--- HTTP_HOST 与 SERVER_NAME 不一致的记录 ---');
db.results.find({ $where: 'this.meta.SERVER.HTTP_HOST != this.meta.SERVER.SERVER_NAME' }, { meta: 1 }).limit(10).forEach(function (d) {
    print(d.meta.request_ts, '| SERVER_NAME=' + d.meta.SERVER.SERVER_NAME, '| HTTP_HOST=' + d.meta.SERVER.HTTP_HOST, '| url=' + d.meta.url);
});
print('--- tp8.local 的记录数 ---');
print(db.results.countDocuments({ 'meta.SERVER.SERVER_NAME': 'tp8.local' }));