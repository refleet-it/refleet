#!/usr/bin/env node

/**
 * Image Optimization Script
 * Converts PNG/JPG images to WebP and AVIF formats for better performance
 */

import sharp from 'sharp';
import { readdir, stat, mkdir } from 'fs/promises';
import { join, extname, basename } from 'path';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const ASSETS_DIR = join(__dirname, '../src/assets/images');
const SUPPORTED_FORMATS = ['.png', '.jpg', '.jpeg'];

// Quality settings
const WEBP_QUALITY = 85;
const AVIF_QUALITY = 70;

// Track statistics
const stats = {
  processed: 0,
  skipped: 0,
  errors: 0,
  originalSize: 0,
  webpSize: 0,
  avifSize: 0,
};

/**
 * Get all image files recursively from directory
 */
async function getImageFiles(dir) {
  const files = [];
  const entries = await readdir(dir, { withFileTypes: true });

  for (const entry of entries) {
    const fullPath = join(dir, entry.name);

    if (entry.isDirectory()) {
      files.push(...(await getImageFiles(fullPath)));
    } else if (SUPPORTED_FORMATS.includes(extname(entry.name).toLowerCase())) {
      files.push(fullPath);
    }
  }

  return files;
}

/**
 * Get file size in bytes
 */
async function getFileSize(filePath) {
  try {
    const stats = await stat(filePath);
    return stats.size;
  } catch {
    return 0;
  }
}

/**
 * Convert image to WebP and AVIF formats
 */
async function optimizeImage(inputPath) {
  const ext = extname(inputPath);
  const base = basename(inputPath, ext);
  const dir = dirname(inputPath);
  const webpPath = join(dir, `${base}.webp`);
  const avifPath = join(dir, `${base}.avif`);

  try {
    const originalSize = await getFileSize(inputPath);
    stats.originalSize += originalSize;

    console.log(`\n📸 Processing: ${inputPath}`);
    console.log(`   Original size: ${(originalSize / 1024).toFixed(2)} KB`);

    // Convert to WebP
    await sharp(inputPath).webp({ quality: WEBP_QUALITY }).toFile(webpPath);

    const webpSize = await getFileSize(webpPath);
    stats.webpSize += webpSize;
    console.log(
      `   ✅ WebP created: ${(webpSize / 1024).toFixed(2)} KB (${((webpSize / originalSize) * 100).toFixed(1)}% of original)`
    );

    // Convert to AVIF
    await sharp(inputPath)
      .avif({ quality: AVIF_QUALITY, effort: 5 })
      .toFile(avifPath);

    const avifSize = await getFileSize(avifPath);
    stats.avifSize += avifSize;
    console.log(
      `   ✅ AVIF created: ${(avifSize / 1024).toFixed(2)} KB (${((avifSize / originalSize) * 100).toFixed(1)}% of original)`
    );

    stats.processed++;
  } catch (error) {
    console.error(`   ❌ Error processing ${inputPath}:`, error.message);
    stats.errors++;
  }
}

/**
 * Main execution
 */
async function main() {
  console.log('🚀 Starting image optimization...\n');
  console.log(`📁 Scanning directory: ${ASSETS_DIR}\n`);

  try {
    const imageFiles = await getImageFiles(ASSETS_DIR);

    if (imageFiles.length === 0) {
      console.log('⚠️  No images found to optimize');
      return;
    }

    console.log(`Found ${imageFiles.length} images to optimize\n`);

    for (const file of imageFiles) {
      await optimizeImage(file);
    }

    // Print summary
    console.log('\n' + '='.repeat(60));
    console.log('📊 Optimization Summary');
    console.log('='.repeat(60));
    console.log(`✅ Successfully processed: ${stats.processed}`);
    console.log(`⏭️  Skipped: ${stats.skipped}`);
    console.log(`❌ Errors: ${stats.errors}`);
    console.log(`\n📦 Size comparison:`);
    console.log(`   Original total: ${(stats.originalSize / 1024).toFixed(2)} KB`);
    console.log(
      `   WebP total:     ${(stats.webpSize / 1024).toFixed(2)} KB (${((stats.webpSize / stats.originalSize) * 100).toFixed(1)}%)`
    );
    console.log(
      `   AVIF total:     ${(stats.avifSize / 1024).toFixed(2)} KB (${((stats.avifSize / stats.originalSize) * 100).toFixed(1)}%)`
    );
    console.log(
      `\n💾 Total space saved: ${((stats.originalSize - stats.avifSize) / 1024).toFixed(2)} KB with AVIF`
    );
    console.log('='.repeat(60));
  } catch (error) {
    console.error('❌ Fatal error:', error);
    process.exit(1);
  }
}

main();
