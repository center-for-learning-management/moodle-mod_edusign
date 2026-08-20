define(['jquery'], function($) {
    return {
        init: function() {
            initialize();
        },
        save: function() {
            initialize();
        }

    };
});


// Works out the X, Y position of the click inside the canvas from the X, Y position on the page

/**
 *
 * @param mouseEvent
 * @param sigCanvas
 */
function getPosition(mouseEvent, sigCanvas) {
    var rect = sigCanvas.getBoundingClientRect();
    console.log(mouseEvent.clientX + " " + rect.left + " " + rect.right + " " + mouseEvent.offsetX + " " + rect.left + " " + sigCanvas.offsetLeft);
    return {
        X: mouseEvent.offsetX,
        Y: mouseEvent.offsetY
    };
}


/**
 *
 */
function initialize() {
    // Get references to the canvas element as well as the 2D drawing context
    var sigCanvas = document.getElementById("canvas");
    var context = sigCanvas.getContext("2d");
    context.strokeStyle = "#000";
    context.lineJoin = "round";
    context.lineWidth = 10;
    $('#clearCanvas').bind('click', function() {
        clearCanvas(sigCanvas, context);
    });
    $('#id_submitbutton').click(function() {
        var data = $('#canvas')[0].toDataURL();// Change here
        $('#id_signing').val(data);
    });


    // This will be defined on a TOUCH device such as iPad or Android, etc.
    var is_touch_device = 'ontouchstart' in document.documentElement;

    if (is_touch_device) {
        // Create a drawer which tracks touch movements
        var drawer = {
            isDrawing: false,
            touchstart: function(coors) {
                context.beginPath();
                context.moveTo(coors.x, coors.y);
                this.isDrawing = true;
            },
            touchmove: function(coors) {
                if (this.isDrawing) {
                    context.lineTo(coors.x, coors.y);
                    context.stroke();
                }
            },
            touchend: function(coors) {
                if (this.isDrawing) {
                    this.touchmove(coors);
                    this.isDrawing = false;
                }
            }
        };

        // Create a function to pass touch events and coordinates to drawer
        /**
         *
         * @param event
         */
        function draw(event) {

            // Get the touch coordinates.  Using the first touch in case of multi-touch
            var coors = {
                // X: event.targetTouches[0].pageX,
                // y: event.targetTouches[0].pageY
                x: mouseEvent.offsetX,
                y: mouseEvent.offsetX
            };

            // Now we need to get the offset of the canvas location
            var obj = sigCanvas;

            if (obj.offsetParent) {
                // Every time we find a new object, we add its offsetLeft and offsetTop to curleft and curtop.
                do {
                    coors.x -= obj.offsetLeft;
                    coors.y -= obj.offsetTop;
                }
                    // The while loop can be "while (obj = obj.offsetParent)" only, which does return null
                    // when null is passed back, but that creates a warning in some editors (i.e. VS2010).
                while ((obj = obj.offsetParent) != null);
            }

            // Pass the coordinates to the appropriate handler
            drawer[event.type](coors);
        }

        // Attach the touchstart, touchmove, touchend event listeners.
        sigCanvas.addEventListener('touchstart', draw, false);
        sigCanvas.addEventListener('touchmove', draw, false);
        sigCanvas.addEventListener('touchend', draw, false);

        // Prevent elastic scrolling
        sigCanvas.addEventListener('touchmove', function(event) {
            event.preventDefault();
        }, false);
    } else {

        // Start drawing when the mousedown event fires, and attach handlers to
        // draw a line to wherever the mouse moves to
        $("#canvas").mousedown(function(mouseEvent) {
            var position = getPosition(mouseEvent, sigCanvas);
            context.moveTo(position.X, position.Y);
            context.beginPath();

            // Attach event handlers
            $(this).mousemove(function(mouseEvent) {
                drawLine(mouseEvent, sigCanvas, context);
            }).mouseup(function(mouseEvent) {
                finishDrawing(mouseEvent, sigCanvas, context);
            }).mouseout(function(mouseEvent) {
                finishDrawing(mouseEvent, sigCanvas, context);
            });
        });

    }
}

// Draws a line to the x and y coordinates of the mouse event inside
// the specified element using the specified context
/**
 *
 * @param mouseEvent
 * @param sigCanvas
 * @param context
 */
function drawLine(mouseEvent, sigCanvas, context) {

    var position = getPosition(mouseEvent, sigCanvas);

    context.lineTo(position.X, position.Y);
    context.stroke();
}

// Draws a line from the last coordiantes in the path to the finishing
// coordinates and unbind any event handlers which need to be preceded
// by the mouse down event
/**
 *
 * @param mouseEvent
 * @param sigCanvas
 * @param context
 */
function finishDrawing(mouseEvent, sigCanvas, context) {
    // Draw the line to the finishing coordinates
    drawLine(mouseEvent, sigCanvas, context);

    context.closePath();

    // Unbind any events which could draw
    $(sigCanvas).unbind("mousemove")
        .unbind("mouseup")
        .unbind("mouseout");
}

// Clear the canvas context using the canvas width and height
/**
 *
 * @param canvas
 * @param ctx
 */
function clearCanvas(canvas, ctx) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

/**
 *
 * @param canvas
 */
function downloadCanvas(canvas) {
    this.href = canvas.toDataURL();

}

/**
 *
 */
function save() {
    this.href = canvas.toDataURL();
    $('#signing').val("this.href");
}
