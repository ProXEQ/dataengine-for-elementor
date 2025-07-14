import { nodeResolve } from '@rollup/plugin-node-resolve';

export default {
    input: 'src/js/editor.js',
    output: {
        file: 'assets/js/editor.bundle.js', 
        format: 'iife' 
    },
    plugins: [
        nodeResolve() 
    ]
};
