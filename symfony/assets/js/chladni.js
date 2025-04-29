/**
 * Chladni Pattern Generator
 * Creates a time-based Chladni pattern visualization that changes throughout the day
 * Only renders when needed (once per second) to minimize CPU usage
 */

// Execute when the DOM is fully loaded
document.addEventListener("DOMContentLoaded", function () {
  initChladni();
});

function initChladni() {
  // Create the Chladni canvas if it doesn't exist
  let canvas = document.getElementById("chladni");
  // Initialize the Chladni pattern generator
  const chladniApp = new ChladniApp(canvas);
}

/**
 * WebGL Renderer for Chladni Patterns
 * Handles all WebGL operations for rendering the patterns
 */
class WebGLRenderer {
  constructor(canvas) {
    this.canvas = canvas;
    this.gl = canvas.getContext("webgl", {
      premultipliedAlpha: false,
      alpha: true,
    });

    if (!this.gl) {
      console.error("WebGL not supported");
      return;
    }

    // Enable alpha blending
    this.gl.enable(this.gl.BLEND);
    this.gl.blendFunc(this.gl.SRC_ALPHA, this.gl.ONE_MINUS_SRC_ALPHA);

    // Set clear color to transparent
    this.gl.clearColor(0, 0, 0, 0);

    // Create shader program
    this.program = this.createProgram(
      this.createVertexShader(),
      this.createFragmentShader(),
    );

    // Use the program
    this.gl.useProgram(this.program);

    // Create vertex buffer
    const buffer = this.gl.createBuffer();
    this.gl.bindBuffer(this.gl.ARRAY_BUFFER, buffer);
    this.gl.bufferData(
      this.gl.ARRAY_BUFFER,
      new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]),
      this.gl.STATIC_DRAW,
    );

    // Set up position attribute
    const position = this.gl.getAttribLocation(this.program, "position");
    this.gl.enableVertexAttribArray(position);
    this.gl.vertexAttribPointer(position, 2, this.gl.FLOAT, false, 0, 0);

    // Get uniform locations
    this.uniforms = {
      time: this.gl.getUniformLocation(this.program, "time"),
      resolution: this.gl.getUniformLocation(this.program, "resolution"),
      params: this.gl.getUniformLocation(this.program, "params"),
    };

    // Set initial size
    this.resize();
    window.addEventListener("resize", () => this.resize());
  }

  createShader(type, source) {
    const shader = this.gl.createShader(type);
    this.gl.shaderSource(shader, source);
    this.gl.compileShader(shader);

    if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
      console.error("Shader compile error:", this.gl.getShaderInfoLog(shader));
      this.gl.deleteShader(shader);
      return null;
    }

    return shader;
  }

  createVertexShader() {
    return this.createShader(
      this.gl.VERTEX_SHADER,
      `
            attribute vec2 position;
            void main() {
                gl_Position = vec4(position, 0.0, 1.0);
            }
        `,
    );
  }

  createFragmentShader() {
    return this.createShader(
      this.gl.FRAGMENT_SHADER,
      `
            precision mediump float;
            uniform vec2 resolution;
            uniform float time;
            uniform vec4 params;
            
            void main(void) {
                const float PI = 3.14159265;
                
                // Get normalized pixel coordinates
                vec2 p = (2.0 * gl_FragCoord.xy - resolution.xy) / resolution.y;
                
                // Get Chladni pattern parameters from uniform
                float a = params[0];
                float b = params[1];
                float n = params[2];
                float m = params[3];
                
                // Calculate Chladni function
                float amp = a * sin(PI * n * p.x) * sin(PI * m * p.y) + 
                           b * sin(PI * m * p.x) * sin(PI * n * p.y);
                
                // Calculate intensity (closer to zero = more intense)
                float intensity = 1.0 - smoothstep(abs(amp), 0.0, 0.1);
                
                // Output white with calculated alpha
                gl_FragColor = vec4(1.0, 1.0, 1.0, intensity);
            }
        `,
    );
  }

  createProgram(vertexShader, fragmentShader) {
    const program = this.gl.createProgram();
    this.gl.attachShader(program, vertexShader);
    this.gl.attachShader(program, fragmentShader);
    this.gl.linkProgram(program);

    if (!this.gl.getProgramParameter(program, this.gl.LINK_STATUS)) {
      console.error("Program link error:", this.gl.getProgramInfoLog(program));
      return null;
    }

    return program;
  }

  resize() {
    // Get the device pixel ratio (for high DPI displays)
    const dpr = window.devicePixelRatio || 1;

    // Get the canvas display size
    const displayWidth = this.canvas.clientWidth;
    const displayHeight = this.canvas.clientHeight;

    // Calculate the size with DPR in mind, but limit resolution for performance
    const maxDimension = Math.max(displayWidth, displayHeight);
    const scaleFactor =
      maxDimension > 1200 ? 0.5 : maxDimension > 800 ? 0.75 : 1.0;

    // Set the canvas internal size
    this.canvas.width = Math.floor(displayWidth * dpr * scaleFactor);
    this.canvas.height = Math.floor(displayHeight * dpr * scaleFactor);

    // Update viewport
    this.gl.viewport(0, 0, this.canvas.width, this.canvas.height);

    // Flag that we need to render
    this.needsRender = true;
  }

  render(time, params) {
    // Clear the canvas
    this.gl.clear(this.gl.COLOR_BUFFER_BIT);

    // Set uniforms
    this.gl.uniform1f(this.uniforms.time, time);
    this.gl.uniform2f(
      this.uniforms.resolution,
      this.canvas.width,
      this.canvas.height,
    );
    this.gl.uniform4f(
      this.uniforms.params,
      params[0],
      params[1],
      params[2],
      params[3],
    );

    // Draw
    this.gl.drawArrays(this.gl.TRIANGLE_STRIP, 0, 4);
  }
}

/**
 * Chladni Visualization Application
 * Handles the main application logic and pattern updates
 */
class ChladniApp {
  constructor(canvas) {
    this.canvas = canvas;

    // Create WebGL renderer
    this.renderer = new WebGLRenderer(canvas);

    // Initialize parameters
    this.lastSecond = -1;
    this.params = [1.0, 1.0, 3.0, 3.0];
    this.needsRender = true;

    // Set up visibility change handler to save resources
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        this.stop();
      } else {
        this.start();
      }
    });

    // Initial parameters update and render
    this.updateParameters();

    // Start update loop
    this.start();
  }

  updateParameters() {
    // Get current time
    const now = new Date();
    const currentSecond = now.getSeconds();

    // Check if second has changed or if we need to force a re-render
    if (currentSecond === this.lastSecond && !this.needsRender) {
      return false;
    }

    // Reset render flag
    this.needsRender = true;

    // Extract time components
    const hours = now.getHours();
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();

    // Calculate day progress (0-1)
    const dayProgress = (hours * 3600 + minutes * 60 + seconds) / 86400;

    // Calculate parameters based on time
    // Each second of the day will have slightly different parameters
    // Full cycle takes 24 hours
    this.params[0] = 1.0 + Math.sin(dayProgress * Math.PI * 2) * 3.0;
    this.params[1] = 1.0 + Math.cos(dayProgress * Math.PI * 2) * 3.0;
    this.params[2] = 3.0 + Math.sin(dayProgress * Math.PI * 4) * 2.0;
    this.params[3] = 3.0 + Math.cos(dayProgress * Math.PI * 4) * 2.0;

    // Update last second
    this.lastSecond = currentSecond;

    return true;
  }

  update() {
    if (!this.isRunning) return;

    // Update parameters based on current time
    const changed = this.updateParameters();

    // Only render if something changed
    if (changed && this.renderer) {
      // Calculate a stable time value for rendering
      const timeValue = this.lastSecond / 60;

      // Render with updated parameters
      this.renderer.render(timeValue, this.params);
    }

    // Continue update loop
    setTimeout(() => this.update(), 100);
  }

  start() {
    if (!this.isRunning) {
      this.isRunning = true;
      this.update();
    }
  }

  stop() {
    this.isRunning = false;
  }
}
