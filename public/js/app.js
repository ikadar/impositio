const stage = new Konva.Stage({
    container: 'konva-container', // id of container <div>
    width: window.innerWidth,
    height: window.innerHeight
});

const scale = 0.9;

const layer = new Konva.Layer();
layer.scale({x: scale, y: scale});
stage.add(layer);

const boundingAreaOffset = {
    x: 120,
    y: 100
}

const show = (placementData) => {

    console.log(placementData);

    layer.removeChildren();

    // bounding area - START
    const boundingAreaRect = new Konva.Rect({
        x: boundingAreaOffset.x,
        y: boundingAreaOffset.y,
        width: placementData.boundingArea.width,
        height: placementData.boundingArea.height,
        fill: 'white',
        // stroke: 'black',
        strokeWidth: 0
    });
    layer.add(boundingAreaRect);

    const gripMarginRect = new Konva.Rect({
        x: boundingAreaOffset.x,
        y: boundingAreaOffset.y,
        width: placementData.boundingArea.width,
        height: placementData.boundingArea["grip-margin"],
        fill: 'red',
        // stroke: 'black',
        strokeWidth: 0
    });
    layer.add(gripMarginRect);

    // dimension lines - START
    layer.add(horizontalDimensionLine({
        x: boundingAreaOffset.x,
        y: boundingAreaOffset.y - 10,
        // color: "red",
        length: placementData.boundingArea.width,
        height: placementData.boundingArea.height + 10 + 30,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 10,
        y: boundingAreaOffset.y,
        length: placementData.boundingArea.height,
        width: placementData.boundingArea.width + 10 + 30,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 30,
        y: boundingAreaOffset.y + placementData.usableArea.y,
        length: placementData.usableArea.height,
        width: placementData.boundingArea.width + 30 + 30,
    }));

    // dimension lines - END
    // bounding area - END

    const placements = placementData.placements;

    // used area - START
    layer.add(horizontalDimensionLine({
        x: boundingAreaOffset.x,
        y: boundingAreaOffset.y - 30,
        length: placementData.usedArea.x,
        height: 30,
    }));

    layer.add(horizontalDimensionLine({
        x: boundingAreaOffset.x + placementData.usedArea.x,
        y: boundingAreaOffset.y - 30,
        // color: "red",
        length: placementData.usedArea.width,
        height: placementData.boundingArea.height + 30 + 30,
    }));

    layer.add(horizontalDimensionLine({
        x: boundingAreaOffset.x + placementData.usedArea.x + placementData.usedArea.width,
        y: boundingAreaOffset.y - 30,
        length: placementData.boundingArea.width - (placementData.usedArea.x + placementData.usedArea.width),
        // length: placementData.usedArea.x,
        height: 30,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 50,
        y: boundingAreaOffset.y + placementData.boundingArea["grip-margin"],
        // color: "red",
        length: placementData.usedArea.y - placementData.boundingArea["grip-margin"],
        width: placementData.boundingArea.width + 50 + 30 ,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 50,
        y: boundingAreaOffset.y + placementData.usedArea.y,
        length: placementData.usedArea.height,
        width: placementData.boundingArea.width + 50 + 30 ,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 50,
        y: boundingAreaOffset.y + placementData.usedArea.y + placementData.usedArea.height,
        // color: "blue",
        length: (placementData.boundingArea.height - placementData.boundingArea["grip-margin"]) - (placementData.usedArea.y + placementData.usedArea.height - placementData.boundingArea["grip-margin"]),
        width: placementData.boundingArea.width + 50 + 30 ,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 30,
        y: boundingAreaOffset.y,
        // color: "blue",
        length: placementData.boundingArea["grip-margin"],
        width: placementData.boundingArea.width + 50 + 30 ,
    }));

    // used area - END

    // first tile with cutBuffer - START
    layer.add(horizontalDimensionLine({
        x: boundingAreaOffset.x + placementData.usedArea.x,
        y: boundingAreaOffset.y - 50,
        length: placementData.firstTileWithCutBuffer.width,
        height: placementData.boundingArea.height + 50 + 30 ,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 70,
        y: boundingAreaOffset.y + placementData.usedArea.y,
        length: placementData.firstTileWithCutBuffer.height,
        width: placementData.boundingArea.width + 70 + 30,
    }));
    // first tile with cutBuffer - END

    // first tile - START
    layer.add( horizontalDimensionLine({
        x: boundingAreaOffset.x + placementData.usedArea.x + placementData.firstTile.x,
        y: boundingAreaOffset.y - 70,
        length: placementData.firstTile.width,
        height: placementData.boundingArea.height + 70 + 30,
    }));

    layer.add(verticalDimensionLine({
        x: boundingAreaOffset.x - 90,
        y: boundingAreaOffset.y + placementData.usedArea.y + placementData.firstTile.y,
        length: placementData.firstTile.height,
        width: placementData.boundingArea.width + 90 + 30,
    }));
    // first tile - END

    layer.add(horizontalTrimLine(placementData.trimLines.top));
    layer.add(horizontalTrimLine(placementData.trimLines.bottom));
    layer.add(verticalTrimLine(placementData.trimLines.left));
    layer.add(verticalTrimLine(placementData.trimLines.right));


    const usedAreaGroup = new Konva.Group({
        x: placementData.usedArea.x,
        y: placementData.usedArea.y,
    });
    layer.add(usedAreaGroup);

    for (let i in placements) {

        let x = boundingAreaRect.x() + placements[i].mmCutBufferPositions.x;
        let y = boundingAreaRect.y() + placements[i].mmCutBufferPositions.y;
        let width = placements[i].mmCutBufferPositions.width;
        let height = placements[i].mmCutBufferPositions.height;

        const tileWithCutBuffer = new Konva.Rect({
            x: x,
            y: y,
            width: width,
            height: height,
            fill: 'orange',
            stroke: 'black',
            strokeWidth: 0.1,
            opacity: 0.5,
        });
        usedAreaGroup.add(tileWithCutBuffer);
        // layer.add(tileWithCutBuffer);

        x = boundingAreaRect.x() + placements[i].mmPositions.x;
        y = boundingAreaRect.y() + placements[i].mmPositions.y;
        width = placements[i].mmPositions.width;
        height = placements[i].mmPositions.height;

        const tile = new Konva.Rect({
            x: x,
            y: y,
            width: width,
            height: height,
            fill: 'gray',
            stroke: 'black',
            strokeWidth: 0.1,
            opacity: 0.5,
        });
        usedAreaGroup.add(tile);


        // const simpleText = new Konva.Text({
        //     x: x,
        //     y: y,
        //     width: width,
        //     height: height,
        //     align: "center",
        //     verticalAlign: "middle",
        //     text: `${placements[i].gridPosition.x};${placements[i].gridPosition.y}`,
        //     fontSize: 12,
        //     fontFamily: 'Helvetica Neue',
        //     fill: 'white',
        //     opacity: 1,
        // });
        // layer.add(simpleText);

        // console.log(placementData[i]);
    }

    stage.add(layer);
}

const horizontalDimensionLine = (options) => {

    const dimensionLineGroup = new Konva.Group({
        x: options.x,
        y: options.y,
    });

    const arrow = new Konva.Arrow({
        x: 0,
        y: 0,
        points: [0, 0, options.length, 0],
        pointerLength: 10,
        pointerWidth: 6,
        pointerAtBeginning: true,
        fill: options.color || 'black',
        stroke: options.color || 'black',
        strokeWidth: 1
    });
    dimensionLineGroup.add(arrow);

    const label = new Konva.Text({
        x: 0,
        y: - 20,
        width: options.length,
        height: 20,
        align: "center",
        verticalAlign: "middle",
        text: `${options.length} mm`,
        fontSize: 12,
        fontFamily: 'Helvetica Neue',
        fill: options.color || 'black',
        opacity: 1,
        rotation: 0,
    });
    dimensionLineGroup.add(label);

    const leftLine = new Konva.Line({
        x: 0,
        y: 0,
        points: [0, 0, 0, options.height],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(leftLine);

    const rightLine = new Konva.Line({
        x: options.length,
        y: 0,
        points: [0, 0, 0, options.height],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(rightLine);

    return dimensionLineGroup;

}

const verticalDimensionLine = (options) => {

    const dimensionLineGroup = new Konva.Group({
        x: options.x,
        y: options.y,
    });

    const arrow = new Konva.Arrow({
        x: 0,
        y: 0,
        points: [0, 0, 0, options.length],
        pointerLength: 10,
        pointerWidth: 6,
        pointerAtBeginning: true,
        fill: options.color || 'black',
        stroke: options.color || 'black',
        strokeWidth: 1
    });
    dimensionLineGroup.add(arrow);

    const label = new Konva.Text({
        x: - 20,
        y: options.length,
        width: Math.max(options.length, 40),
        height: 20,
        align: "center",
        verticalAlign: "middle",
        text: `${options.length} mm`,
        fontSize: 12,
        fontFamily: 'Helvetica Neue',
        fill: options.color || 'black',
        opacity: 1,
        rotation: 270,
    });
    dimensionLineGroup.add(label);

    const topLine = new Konva.Line({
        x: 0,
        y: 0,
        points: [0, 0, options.width, 0],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(topLine);

    const bottomLine = new Konva.Line({
        x: 0,
        y: options.length,
        points: [0, 0, options.width, 0],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(bottomLine);

    return dimensionLineGroup;

}

const horizontalTrimLine = (options) => {

    return new Konva.Line({
        x: boundingAreaOffset.x + options.x,
        y: boundingAreaOffset.y + options.y,
        points: [-30, 0, options.length + 30, 0],
        stroke: 'black',
        strokeWidth: 1.5,
        dash: [15, 5]
    })
}

const verticalTrimLine = (options) => {
    return new Konva.Line({
        x: boundingAreaOffset.x + options.x,
        y: boundingAreaOffset.y + options.y,
        points: [0, -30, 0, options.length + 30],
        stroke: 'black',
        strokeWidth: 1.5,
        dash: [15, 5]
    })

}

const calc = (input) => {

    fetch('/test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(input)
    })
        .then(response => response.json())
        .then(data => {

            // console.log(data);
            layer.removeChildren();

            const variations = document.getElementById("variations");
            variations.innerHTML = "";

            for (let i in data) {
                const variation = document.createElement("button");
                variation.innerHTML = data[i].size;
                variation.onclick = () => {
                    show(data[i]);
                }
                variations.appendChild(variation);
            }

        })
        .catch(error => {
            console.error('Error loading JSON:', error);
        });
}


// 👇 Expose it globally
window.calc = calc;