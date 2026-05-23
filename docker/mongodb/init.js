// MongoDB initialization script
// Runs once on first container start

const dbName = process.env.MONGO_INITDB_DATABASE || 'ets_language_exams';
const appUser = process.env.MONGO_APP_USER || 'ets_user';
const appPassword = process.env.MONGO_APP_PASSWORD || 'ets_password';

db = db.getSiblingDB(dbName);

// Create application user with restricted permissions
db.createUser({
  user: appUser,
  pwd: appPassword,
  roles: [{ role: 'readWrite', db: dbName }],
});

// Create collections
db.createCollection('users');
db.createCollection('sessions');
db.createCollection('reservations');

// ─── Indexes ──────────────────────────────────────────────────────────────────
// Unique email for users
db.users.createIndex({ email: 1 }, { unique: true });

// Prevent duplicate bookings (one user per session)
db.reservations.createIndex(
  { session_id: 1, user_id: 1 },
  { unique: true }
);

// Speed up session date-based queries
db.sessions.createIndex({ date: 1 });
db.sessions.createIndex({ language: 1 });

// Speed up user reservation lookups
db.reservations.createIndex({ user_id: 1 });
db.reservations.createIndex({ session_id: 1 });

print('MongoDB initialization complete — database: ' + dbName);
