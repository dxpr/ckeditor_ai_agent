const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Path to your destination directory
const destinationDir = path.join(__dirname, 'js/ckeditor5_plugins/aiagent/src');

// Ensure the destination directory exists
if (!fs.existsSync(destinationDir)) {
  fs.mkdirSync(destinationDir, { recursive: true });
}

try {
  console.log('Installing @dxpr/ckeditor5-ai-agent package...');
  // Install the package locally in a temporary location
  execSync('npm install @dxpr/ckeditor5-ai-agent --no-save', { stdio: 'inherit' });

  console.log('Copying files from @dxpr/ckeditor5-ai-agent/src to destination...');
  // Source directory inside the package
  const sourceDir = path.join(
    __dirname,
    'node_modules/@dxpr/ckeditor5-ai-agent/src'
  );

  if (!fs.existsSync(sourceDir)) {
    throw new Error(
      `Source directory not found: ${sourceDir}. Ensure the package structure is correct.`
    );
  }

  // Copy files recursively from source to destination
  fs.readdirSync(sourceDir).forEach((file) => {
    const sourceFile = path.join(sourceDir, file);
    const destFile = path.join(destinationDir, file);

    // Check if the item is a file and has .js or .ts extension
    if (fs.lstatSync(sourceFile).isFile() && /\.(js|ts)$/.test(file)) {
      fs.copyFileSync(sourceFile, destFile);
      console.log(`Copied: ${file}`);
    }
  });

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

} catch (error) {
  console.error('Error during build process:', error.message);
  process.exit(1);
}
