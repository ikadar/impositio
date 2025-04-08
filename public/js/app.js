const stage = new Konva.Stage({
    container: 'konva-container', // id of container <div>
    width: window.innerWidth,
    height: 5000
    // height: window.innerHeight
});

const scale = 0.8;

let baseLayer = null;

const show = (sheetLayout, machineId, machineIndex) => {

    const layerId = `baseLayer.${machineId}`;

    const layer = new Konva.Layer({
        x: 0,
        y: machineIndex * 800,
        id: layerId,
        scaleX: scale,
        scaleY: scale,
    });
    stage.add(layer);

    baseLayer = stage.findOne(`#${layerId}`);
    baseLayer.removeChildren();

    // const baseLayer = stage.findOne("#baseLayer");


    // console.log(sheetLayout);

    baseLayer.removeChildren();

    showPressSheet(sheetLayout);
    showCutSheet(sheetLayout);
    showTrimLines(sheetLayout);
    showlayoutArea(sheetLayout);
    showMaxSheet(sheetLayout);
    showMinSheet(sheetLayout);

    let distance = 0;
    showPressSheetDimensionLines(sheetLayout, {
        distance: distance++,
        color: "black"
    });
    showTrimDimensionLines(sheetLayout, {
        distance: distance++,
        color: "black"
    });
    // showMaxSheetDimensionLines(sheetLayout, {
    //     distance: distance++,
    //     color: "black",
    // });
    // showMinSheetDimensionLines(sheetLayout, {
    //     distance: distance++,
    //     color: "black",
    // });
    showLayoutAreaDimensionLines(sheetLayout, {
        distance: distance++,
        color: "black"
    });
    showFirstTileWithCutBufferDimensionLines(sheetLayout, {
        distance: distance++,
        color: "black"
    });
    showFirstTileDimensionLines(sheetLayout, {
        distance: distance++,
        color: "black"
    });



    stage.add(baseLayer);
}

const showPressSheet = (sheetLayout) => {

    const sheetOffset = {
        x: 150,
        y: 150
    }

    const pressSheetGroup = new Konva.Group({
        id: "pressSheetGroup",
        x: sheetOffset.x,
        y: sheetOffset.y,
    });
    baseLayer.add(pressSheetGroup);

    const pressSheetRect = new Konva.Rect({
        id: "pressSheet",
        x: 0,
        y: 0,
        width: sheetLayout.pressSheet.width,
        height: sheetLayout.pressSheet.height,
        fill: 'white',
        // stroke: 'black',
        strokeWidth: 0
    });
    pressSheetGroup.add(pressSheetRect);

}

const showPressSheetDimensionLines = (sheetLayout, options) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine({
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.pressSheet.height,
    }));

}

const showTrimDimensionLines = (sheetLayout, options) => {
    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine({
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.trimLines.left.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.trimLines.left.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.trimLines.right.x - sheetLayout.trimLines.left.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.trimLines.right.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width - (sheetLayout.trimLines.right.x),
    }));


    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.trimLines.top.y,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.trimLines.top.y,
        color: options.color,
        length: sheetLayout.trimLines.bottom.y - sheetLayout.trimLines.top.y,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.trimLines.bottom.y,
        color: options.color,
        length: sheetLayout.pressSheet.height - (sheetLayout.trimLines.bottom.y),
    }));

}
const showMaxSheet = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const maxSheetGroup = new Konva.Group({
        id: "maxSheetGroup",
        x: sheetLayout.maxSheet.x,
        y: sheetLayout.maxSheet.y,
    });
    pressSheetGroup.add(maxSheetGroup);

    const maxSheetRect = new Konva.Rect({
        x: 0,
        y: 0,
        width: sheetLayout.maxSheet.width,
        height: sheetLayout.maxSheet.height,
        stroke: "red",
        strokeWidth: 1,
        opacity: 1,
    });
    maxSheetGroup.add(maxSheetRect);

}

const showMaxSheetDimensionLines = (sheetLayout, options) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine({
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.maxSheet.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.maxSheet.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.maxSheet.width,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.maxSheet.x + sheetLayout.maxSheet.width,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width - (sheetLayout.maxSheet.x + sheetLayout.maxSheet.width),
    }));



    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.maxSheet.y,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.maxSheet.y,
        color: options.color,
        length: sheetLayout.maxSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.maxSheet.y + sheetLayout.maxSheet.height,
        color: options.color,
        length: sheetLayout.pressSheet.height - (sheetLayout.maxSheet.y + sheetLayout.maxSheet.height),
    }));

}

const showMinSheet = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const minSheetGroup = new Konva.Group({
        id: "minSheetGroup",
        x: sheetLayout.minSheet.x,
        y: sheetLayout.minSheet.y,
    });
    pressSheetGroup.add(minSheetGroup);

    const minSheetRect = new Konva.Rect({
        x: 0,
        y: 0,
        width: sheetLayout.minSheet.width,
        height: sheetLayout.minSheet.height,
        stroke: "#f4a70c",
        strokeWidth: 1,
        opacity: 1,
    });
    minSheetGroup.add(minSheetRect);
}

const showMinSheetDimensionLines = (sheetLayout, options) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine({
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.minSheet.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.minSheet.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.minSheet.width,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.minSheet.x + sheetLayout.minSheet.width,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width - (sheetLayout.minSheet.x + sheetLayout.minSheet.width),
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.minSheet.y,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.minSheet.y,
        color: options.color,
        length: sheetLayout.minSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.minSheet.y + sheetLayout.minSheet.height,
        color: options.color,
        length: sheetLayout.pressSheet.height - (sheetLayout.minSheet.y + sheetLayout.minSheet.height),
    }));

}

const showlayoutArea = (sheetLayout) => {
    showTiles(sheetLayout);
}

const showTiles = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const layoutAreaGroup = new Konva.Group({
        id: "layoutAreaGroup",
        x: sheetLayout.layoutArea.x,
        y: sheetLayout.layoutArea.y,
    });
    pressSheetGroup.add(layoutAreaGroup);

    const tiles = sheetLayout.tiles;

    for (let i in tiles) {

        let x = tiles[i].mmCutBufferPositions.x;
        let y = tiles[i].mmCutBufferPositions.y;
        let width = tiles[i].mmCutBufferPositions.width;
        let height = tiles[i].mmCutBufferPositions.height;

        const tileWithCutBuffer = new Konva.Rect({
            x: x,
            y: y,
            width: width,
            height: height,
            // fill: 'orange',
            stroke: 'black',
            strokeWidth: 0.1,
            opacity: 1,
        });
        layoutAreaGroup.add(tileWithCutBuffer);

        x = tiles[i].mmPositions.x;
        y = tiles[i].mmPositions.y;
        width = tiles[i].mmPositions.width;
        height = tiles[i].mmPositions.height;

        const tile = new Konva.Rect({
            x: x,
            y: y,
            width: width,
            height: height,
            fill: '#eee8f5',
            // fill: '#8d8d8d',
            stroke: 'black',
            strokeWidth: 0.1,
            opacity: 1,
        });
        layoutAreaGroup.add(tile);


        // const simpleText = new Konva.Text({
        //     x: x,
        //     y: y,
        //     width: width,
        //     height: height,
        //     align: "center",
        //     verticalAlign: "middle",
        //     text: `${tiles[i].gridPosition.x};${tiles[i].gridPosition.y}`,
        //     fontSize: 12,
        //     fontFamily: 'Helvetica Neue',
        //     fill: 'white',
        //     opacity: 1,
        // });
        // baseLayer.add(simpleText);

        // console.log(sheetLayout[i]);
    }

}

const showFirstTileWithCutBufferDimensionLines = (sheetLayout, options) => {
    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.firstTileWithCutBuffer.width,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.layoutArea.y,
        color: options.color,
        length: sheetLayout.firstTileWithCutBuffer.height,
    }));

}

const showFirstTileDimensionLines = (sheetLayout, options) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x + sheetLayout.firstTile.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.firstTile.width,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.layoutArea.y + sheetLayout.firstTile.y,
        color: options.color,
        length: sheetLayout.firstTile.height,
    }));

}

const showLayoutAreaDimensionLines = (sheetLayout, options) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    // used area - START
    pressSheetGroup.add(horizontalDimensionLine({
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.layoutArea.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.layoutArea.width,
    }));

    pressSheetGroup.add(horizontalDimensionLine({
        x: sheetLayout.layoutArea.x + sheetLayout.layoutArea.width,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width - (sheetLayout.layoutArea.x + sheetLayout.layoutArea.width),
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.layoutArea.y,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.layoutArea.y,
        color: options.color,
        length: sheetLayout.layoutArea.height,
    }));

    pressSheetGroup.add(verticalDimensionLine({
        distance: options.distance,
        y: sheetLayout.layoutArea.y + sheetLayout.layoutArea.height,
        color: options.color,
        length: (sheetLayout.pressSheet.height) - (sheetLayout.layoutArea.y + sheetLayout.layoutArea.height),
    }));

}

const showTrimLines = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalTrimLine(sheetLayout.trimLines.top));
    pressSheetGroup.add(horizontalTrimLine(sheetLayout.trimLines.bottom));
    pressSheetGroup.add(verticalTrimLine(sheetLayout.trimLines.left));
    pressSheetGroup.add(verticalTrimLine(sheetLayout.trimLines.right));
}

const showCutSheet = (sheetLayout) => {

    const pressSheetGroup = baseLayer.findOne("#pressSheetGroup");

    const cutSheetRect = new Konva.Rect({
        id: "cutSheetRect",
        x: sheetLayout.cutSheet.x,
        y: sheetLayout.cutSheet.y,
        width: sheetLayout.cutSheet.width,
        height: sheetLayout.cutSheet.height,
        // fill: "yellow",
        fill: "#f1dde6",
        // stroke: "red",
        // strokeWidth: 1,
        opacity: 1,
    });
    pressSheetGroup.add(cutSheetRect);

    const cutSheetGripMarginRect = new Konva.Rect({
        id: "cutSheetGripMarginRect",
        x: sheetLayout.cutSheet.gripMargin.x,
        y: sheetLayout.cutSheet.gripMargin.y,
        width: sheetLayout.cutSheet.gripMargin.width,
        height: sheetLayout.cutSheet.gripMargin.height,
        fill: "#cccdcd",
        opacity: 1

    });
    pressSheetGroup.add(cutSheetGripMarginRect);

}

// generic

const horizontalDimensionLine = (options) => {

    const pressSheet = baseLayer.findOne("#pressSheet");

    options.y = -1 * (10 + (options.distance * 20));
    options.height = pressSheet.attrs.height;

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
        points: [0, 0, 0, options.height - options.y + 30],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(leftLine);

    const rightLine = new Konva.Line({
        x: options.length,
        y: 0,
        points: [0, 0, 0, options.height - options.y + 30],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(rightLine);

    // label.on('pointerenter', function () {
    //     arrow.fill("red");
    //     label.fill("red");
    //     leftLine.stroke("red");
    //     rightLine.stroke("red");
    // });
    //
    // label.on('pointerleave', function () {
    //     arrow.fill(options.color || 'black');
    //     label.fill(options.color || 'black');
    //     leftLine.stroke(options.color || 'black');
    //     rightLine.stroke(options.color || 'black');
    // });


    return dimensionLineGroup;

}

const verticalDimensionLine = (options) => {

    const pressSheet = baseLayer.findOne("#pressSheet");

    options.x = -1 * (10 + (options.distance * 20));
    options.width = pressSheet.attrs.width;

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
        points: [0, 0, options.width - options.x + 30, 0],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(topLine);

    const bottomLine = new Konva.Line({
        x: 0,
        y: options.length,
        points: [0, 0, options.width - options.x + 30, 0],
        stroke: options.color || 'black',
        strokeWidth: 0.5,
        dash: [3, 3]
    });
    dimensionLineGroup.add(bottomLine);

    return dimensionLineGroup;

}

const horizontalTrimLine = (options) => {

    return new Konva.Line({
        x: options.x,
        y: options.y,
        points: [-30, 0, options.length + 30, 0],
        stroke: 'black',
        strokeWidth: 1.5,
        dash: [15, 5]
    })
}

const verticalTrimLine = (options) => {
    return new Konva.Line({
        x: options.x,
        y: options.y,
        points: [0, -30, 0, options.length + 30],
        stroke: 'black',
        strokeWidth: 1.5,
        dash: [15, 5]
    })

}

// let machineIndex = 0;

const calc = (input, machineIndex) => {

    if (machineIndex < input.machines.length) {

        input.machine = input.machines[machineIndex];

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
                // baseLayer.removeChildren();

                displayMachineVariations(data, input, machineIndex);

            })
            .catch(error => {
                console.error('Error loading JSON:', error);
            });
        }
}

const displayMachineVariations = (data, input, machineIndex) => {
    const display = document.getElementById("display");
    // display.innerHTML = "";

    const variations = document.createElement("div");
    variations.id = `variations-${machineIndex}`;
    variations.innerHTML = "";
    display.appendChild(variations);

    const rotatedVariations = document.createElement("div");
    rotatedVariations.id = `rotated-variations-${machineIndex}`;
    rotatedVariations.innerHTML = "";
    rotatedVariations.style.marginBottom = "50px";
    display.appendChild(rotatedVariations);

    // if (machineIndex < input.machines.length) {

        for (let i in data) {
            const variation = document.createElement("button");
            variation.innerHTML = data[i].size;
            variation.onclick = () => {
                show(data[i], input.machines[machineIndex].id, machineIndex);

                // machineIndex++;

                input.zone = {
                    width: data[i].cutSheet.width,
                    height: data[i].cutSheet.height,
                }

                // console.log(input);
                // console.log(data[i]);

                    calc(input, machineIndex+1);


            }
            if (data[i].rotated) {
                rotatedVariations.appendChild(variation);
            } else {
                variations.appendChild(variation);
            }
        }
    // }
}


// 👇 Expose it globally
window.calc = calc;