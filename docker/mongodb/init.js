/**
 * MongoDB init script — runs once when the container is first created.
 *
 * Responsibility: create the application user only.
 * Collections and indexes are owned by the application:
 *   • `make schema`   → doctrine:mongodb:schema:create (reads ODM annotations)
 *   • `make fixtures` → doctrine:mongodb:fixtures:load  (seeds data)
 */
const dbName  = process.env.MONGO_INITDB_DATABASE || 'ets_language_exams';
const appUser = process.env.MONGO_APP_USER        || 'ets_user';
const appPwd  = process.env.MONGO_APP_PASSWORD    || 'ets_password';

db = db.getSiblingDB(dbName);

db.createUser({
    user:  appUser,
    pwd:   appPwd,
    roles: [{ role: 'readWrite', db: dbName }],
});

print('MongoDB init complete — app user created for db: ' + dbName);
