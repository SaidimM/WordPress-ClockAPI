#!/usr/bin/env node

/**
 * Database initialization script
 * Run this script to initialize the database manually
 */

import { initDatabase, closeDatabase } from './db.js';

try {
  console.log('Initializing database...');
  initDatabase();
  console.log('Database initialization completed successfully!');
  closeDatabase();
  process.exit(0);
} catch (error) {
  console.error('Database initialization failed:', error);
  process.exit(1);
}
