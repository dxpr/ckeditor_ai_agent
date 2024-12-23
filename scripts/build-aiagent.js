const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Path to your destination directory
const destinationDir = path.join(__dirname, '../js/ckeditor5_plugins/aiagent/src');

// Ensure the destination directory exists
if (!fs.existsSync(destinationDir)) {
  fs.mkdirSync(destinationDir, { recursive: true });
}

try {
  // First, remove only the ai-agent.js file from build directory
  const buildDir = path.join(__dirname, '../js/build');
  const buildFile = path.join(buildDir, 'ai-agent.js');

  if (!fs.existsSync(buildFile)) {
    console.log('Removing existing build file...');
    fs.unlinkSync(buildFile);
  }

  // Ensure build directory exists
  if (!fs.existsSync(buildDir)) {
    fs.mkdirSync(buildDir, { recursive: true });
  }
  console.log('Build directory ready.');

  // Copy translations from package build directory
  const sourceTranslationsDir = path.join(
    __dirname,
    '../node_modules/@dxpr/ckeditor5-ai-agent/build/translations'
  );
  const destTranslationsDir = path.join(buildDir, 'translations');

  if (fs.existsSync(sourceTranslationsDir)) {
    if (!fs.existsSync(destTranslationsDir)) {
      fs.mkdirSync(destTranslationsDir, { recursive: true });
    }
    copyRecursively(sourceTranslationsDir, destTranslationsDir);
    console.log('Translations successfully copied.');
  }

  // Remove existing destination directory
  if (fs.existsSync(destinationDir)) {
    console.log('Removing existing destination directory...');
    fs.rmSync(destinationDir, { recursive: true, force: true });
  }
  fs.mkdirSync(destinationDir, { recursive: true });

  console.log('Installing @dxpr/ckeditor5-ai-agent package...');
  execSync('npm install @dxpr/ckeditor5-ai-agent', { stdio: 'inherit' });

  console.log('Copying files from @dxpr/ckeditor5-ai-agent/src to destination...');
  // Source directory inside the package
  const sourceDir = path.join(
    __dirname,
    '../node_modules/@dxpr/ckeditor5-ai-agent/src'
  );

  if (!fs.existsSync(sourceDir)) {
    throw new Error(
      `Source directory not found: ${sourceDir}. Ensure the package structure is correct.`
    );
  }

  // Copy files recursively from source to destination
  function copyRecursively(source, destination) {
    // Create destination directory if it doesn't exist
    if (!fs.existsSync(destination)) {
      fs.mkdirSync(destination, { recursive: true });
    }

    // Read source directory
    const files = fs.readdirSync(source);

    files.forEach(file => {
      const sourcePath = path.join(source, file);
      const destPath = path.join(destination, file);

      const stats = fs.lstatSync(sourcePath);

      if (stats.isDirectory()) {
        // Recursively copy subdirectories
        console.log(`Creating directory: ${destPath}`);
        copyRecursively(sourcePath, destPath);
      } else {
        // Copy all files
        fs.copyFileSync(sourcePath, destPath);
        console.log(`Copied file: ${sourcePath} -> ${destPath}`);
      }
    });
  }

  copyRecursively(sourceDir, destinationDir);

  console.log('Files successfully copied to destination directory.');

  // Paths to specific files to modify
  const aiagentFile = path.join(destinationDir, 'aiagent.js');
  const indexFile = path.join(destinationDir, 'index.js');

  // Modify aiagent.js
  if (fs.existsSync(aiagentFile)) {
    let content = fs.readFileSync(aiagentFile, 'utf8');
    // Comment out the import statement for style.css
    content = content.replace(
      /import\s+['"]\.\.\/theme\/style\.css['"];\n/,
      ''
    );
    fs.writeFileSync(aiagentFile, content, 'utf8');
    console.log('Modified aiagent.js');
  } else {
    console.warn('aiagent.js not found, skipping modification.');
  }

  // Modify index.js
  if (fs.existsSync(indexFile)) {
    let content = fs.readFileSync(indexFile, 'utf8');
    // Replace export statement with import statement
    content = content.replace(
      /export\s+\{\s+default\s+as\s+AiAgent\s+\}\s+from\s+['"]\.\/aiagent\.js['"];\n/,
      'import AiAgent from "./aiagent.js";\n'
    );
    // Add new export at the end of the file
    if (!content.endsWith('\n')) {
      content += '\n';
    }
    content += "\nexport default {\n  AiAgent,\n};\n";
    fs.writeFileSync(indexFile, content, 'utf8');
    console.log('Modified index.js');
  } else {
    console.warn('index.js not found, skipping modification.');
  }

  // Update theme directory copying to handle icons subdirectory
  const sourceThemeDir = path.join(
    __dirname,
    '../node_modules/@dxpr/ckeditor5-ai-agent/theme'
  );
  const destThemeDir = path.join(__dirname, '../js/ckeditor5_plugins/aiagent/theme');

  if (fs.existsSync(sourceThemeDir)) {
    if (!fs.existsSync(destThemeDir)) {
      fs.mkdirSync(destThemeDir, { recursive: true });
    }

    // Copy theme files and maintain directory structure
    copyRecursively(sourceThemeDir, destThemeDir);
    console.log('Theme directory successfully copied.');
  } else {
    console.warn('Theme directory not found in source package.');
  }

} catch (error) {
  console.error('Error during build process:', error.message);
  process.exit(1);
}
