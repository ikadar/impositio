const stage = new Konva.Stage({
    container: 'konva-container', // id of container <div>
    width: window.innerWidth,
    height: 0
    // height: 400
    // height: window.innerHeight
});

const scale = 0.8;

let baseLayer = new Konva.Layer({
    x: 0,
    y: 0,
    id: "base",
    scaleX: scale,
    scaleY: scale,
});
stage.add(baseLayer);

const show = (sheetLayout, machineGroup, content, actionPath) => {

    // console.log(content);

    let pressSheetGroup = machineGroup.findOne("#pressSheetGroup");
    if (pressSheetGroup) {
        pressSheetGroup.remove();
    }

    showExplanation(sheetLayout, machineGroup, actionPath);

    showPressSheet(sheetLayout, machineGroup);
    pressSheetGroup = machineGroup.findOne("#pressSheetGroup");
    showTrimLines(sheetLayout, machineGroup);
    showCutSheet(sheetLayout, machineGroup);
    showLayoutArea(sheetLayout, machineGroup, content, actionPath);
    showMaxSheet(sheetLayout, machineGroup);
    showMinSheet(sheetLayout, machineGroup);

    let distance = 0;
    showPressSheetDimensionLines(sheetLayout, machineGroup, {
        distance: distance++,
        color: "black"
    });
    showTrimDimensionLines(sheetLayout, machineGroup, {
        distance: distance++,
        color: "black"
    });
    // showMaxSheetDimensionLines(sheetLayout, machineGroup, {
    //     distance: distance++,
    //     color: "black",
    // });
    // showMinSheetDimensionLines(sheetLayout, machineGroup, {
    //     distance: distance++,
    //     color: "black",
    // });
    showLayoutAreaDimensionLines(sheetLayout, machineGroup, {
        distance: distance++,
        color: "red"
    });
    showFirstTileWithCutBufferDimensionLines(sheetLayout, machineGroup, {
        distance: distance++,
        color: "black"
    });
    showFirstTileDimensionLines(sheetLayout, machineGroup, {
        distance: distance++,
        color: "black"
    });

    return cloneContent(pressSheetGroup);
}

const cloneContent = (pressSheetGroup) => {

    const contentGroup = new Konva.Group({
        id: "contentGroup",
        x: 0,
        y: 0
    });

    const cutSheetRect = pressSheetGroup.findOne("#cutSheetRect");
    // console.log(cutSheetRect.attrs);
    contentGroup.add(cutSheetRect.clone({
        x: 0,
        y: 0,
        // fill: "#dfe8eb"
    }));

    const cutSheetGripMarginRect = pressSheetGroup.findOne("#cutSheetGripMarginRect");
    contentGroup.add(cutSheetGripMarginRect.clone({x: 0, y: 0, fill: "#dfe8eb"}));

    const layoutAreaGroup = pressSheetGroup.findOne("#layoutAreaGroup");
    contentGroup.add(layoutAreaGroup.clone({
        x: layoutAreaGroup.attrs.x - cutSheetRect.attrs.x,
        y: layoutAreaGroup.attrs.y - cutSheetRect.attrs.y,
    }));

    return contentGroup;
}

const showExplanation = (sheetLayout, machineGroup, actionPath) => {

    // console.log(actionPath);

    const existingExplanationGroup = baseLayer.findOne(`#explanationGroup-${machineGroup.attrs.id}`);
    if (existingExplanationGroup) {
        existingExplanationGroup.remove();
    }


    const explanationGroup = new Konva.Group({
        id: `explanationGroup-${machineGroup.attrs.id}`,
        x: 0,
        y: 0,
    });
    machineGroup.add(explanationGroup);


    const machine = new Konva.Text({
        x: 20,
        y: 200,
        width: 300,
        height: 20,
        align: "left",
        verticalAlign: "middle",
        text: `Machine: ${sheetLayout.explanation.machine.name}`,
        fontSize: 16,
        fontFamily: 'Helvetica Neue',
        fill: 'black',
        opacity: 1,
        rotation: 0,
    });
    explanationGroup.add(machine);

    const machineMaxConstraints = new Konva.Text({
        x: 20,
        y: 220,
        width: 300,
        height: 20,
        align: "left",
        verticalAlign: "middle",
        text: `max sheet: ${sheetLayout.explanation.machine.maxSheet.width}mm x ${sheetLayout.explanation.machine.maxSheet.height}mm`,
        fontSize: 16,
        fontFamily: 'Helvetica Neue',
        fill: 'black',
        opacity: 1,
        rotation: 0,
    });
    explanationGroup.add(machineMaxConstraints);

    const machineMinConstraints = new Konva.Text({
        x: 20,
        y: 240,
        width: 300,
        height: 20,
        align: "left",
        verticalAlign: "middle",
        text: `min sheet: ${sheetLayout.explanation.machine.minSheet.width}mm x ${sheetLayout.explanation.machine.minSheet.height}mm`,
        fontSize: 16,
        fontFamily: 'Helvetica Neue',
        fill: 'black',
        opacity: 1,
        rotation: 0,
    });
    explanationGroup.add(machineMinConstraints);

    // const cutSheetSize = new Konva.Text({
    //     x: 320,
    //     y: 200,
    //     width: 300,
    //     height: 20,
    //     align: "left",
    //     verticalAlign: "middle",
    //     text: `Output sheet: ${sheetLayout.cutSheet.width}mm x ${sheetLayout.cutSheet.height}mm`,
    //     fontSize: 16,
    //     fontFamily: 'Helvetica Neue',
    //     fill: 'black',
    //     opacity: 1,
    //     rotation: 0,
    // });
    // explanationGroup.add(cutSheetSize);
    //
    // const cutSheetCount = new Konva.Text({
    //     x: 320,
    //     y: 220,
    //     width: 300,
    //     height: 20,
    //     align: "left",
    //     verticalAlign: "middle",
    //     text: `Number of items: ${sheetLayout.cols*sheetLayout.rows}`,
    //     fontSize: 16,
    //     fontFamily: 'Helvetica Neue',
    //     fill: 'black',
    //     opacity: 1,
    //     rotation: 0,
    // });
    // explanationGroup.add(cutSheetCount);
    //
    // const poseRotation = new Konva.Text({
    //     x: 320,
    //     y: 240,
    //     width: 300,
    //     height: 20,
    //     align: "left",
    //     verticalAlign: "middle",
    //     text: `Rotation: ${sheetLayout.rotated ? "Yes" : "No"}`,
    //     fontSize: 16,
    //     fontFamily: 'Helvetica Neue',
    //     fill: 'black',
    //     opacity: 1,
    //     rotation: 0,
    // });
    // explanationGroup.add(poseRotation);

}

const showPressSheet = (sheetLayout, machineGroup) => {

    const sheetOffset = {
        x: 150,
        y: 400
    }

    const pressSheetGroup = new Konva.Group({
        id: "pressSheetGroup",
        x: sheetOffset.x,
        y: sheetOffset.y,
    });
    machineGroup.add(pressSheetGroup);

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

const showPressSheetDimensionLines = (sheetLayout, machineGroup, options) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.pressSheet.height,
    }));

}

const showTrimDimensionLines = (sheetLayout, machineGroup, options) => {
    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.trimLines.left.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.trimLines.left.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.trimLines.right.x - sheetLayout.trimLines.left.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.trimLines.right.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width - (sheetLayout.trimLines.right.x),
    }));


    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.trimLines.top.y,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.trimLines.top.y,
        color: options.color,
        length: sheetLayout.trimLines.bottom.y - sheetLayout.trimLines.top.y,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.trimLines.bottom.y,
        color: options.color,
        length: sheetLayout.pressSheet.height - (sheetLayout.trimLines.bottom.y),
    }));

}
const showMaxSheet = (sheetLayout, machineGroup) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

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

const showMaxSheetDimensionLines = (sheetLayout, machineGroup, options) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.maxSheet.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.maxSheet.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.maxSheet.width,
    }));

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.maxSheet.x + sheetLayout.maxSheet.width,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width - (sheetLayout.maxSheet.x + sheetLayout.maxSheet.width),
    }));



    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.maxSheet.y,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.maxSheet.y,
        color: options.color,
        length: sheetLayout.maxSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.maxSheet.y + sheetLayout.maxSheet.height,
        color: options.color,
        length: sheetLayout.pressSheet.height - (sheetLayout.maxSheet.y + sheetLayout.maxSheet.height),
    }));

}

const showMinSheet = (sheetLayout, machineGroup) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

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

const showMinSheetDimensionLines = (sheetLayout, machineGroup, options) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: 0,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.minSheet.x,
    }));

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.minSheet.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.minSheet.width,
    }));

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.minSheet.x + sheetLayout.minSheet.width,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.pressSheet.width - (sheetLayout.minSheet.x + sheetLayout.minSheet.width),
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: 0,
        color: options.color,
        length: sheetLayout.minSheet.y,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.minSheet.y,
        color: options.color,
        length: sheetLayout.minSheet.height,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.minSheet.y + sheetLayout.minSheet.height,
        color: options.color,
        length: sheetLayout.pressSheet.height - (sheetLayout.minSheet.y + sheetLayout.minSheet.height),
    }));

}

const showLayoutArea = (sheetLayout, machineGroup, content, actionPath) => {
    showTiles(sheetLayout, machineGroup, content, actionPath);
}

const showTiles = (sheetLayout, machineGroup, content, actionPath) => {

    // console.log(sheetLayout);

    const keys = Object.keys(actionPath);
    const lastMachineKey = keys[keys.length - 1];

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    const layoutAreaGroup = new Konva.Group({
        id: "layoutAreaGroup",
        x: sheetLayout.layoutArea.x,
        y: sheetLayout.layoutArea.y,
    });
    pressSheetGroup.add(layoutAreaGroup);

    // const layoutAreaRect = new Konva.Rect({
    //     x: 0,
    //     y: 0,
    //     width: sheetLayout.layoutArea.width,
    //     height: sheetLayout.layoutArea.height,
    //     fill: 'orange',
    //     // stroke: 'black',
    //     // strokeWidth: 0.1,
    //     opacity: 0.8,
    // });
    // layoutAreaGroup.add(layoutAreaRect);

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
            stroke: 'green',
            strokeWidth: 2,
            // stroke: 'black',
            // strokeWidth: 0.1,
            opacity: 1,
        });
        layoutAreaGroup.add(tileWithCutBuffer);

        x = tiles[i].mmPositions.x;
        y = tiles[i].mmPositions.y;
        width = tiles[i].mmPositions.width;
        height = tiles[i].mmPositions.height;

        const tileGroup = new Konva.Group({
            x: x,
            y: y,
        });

        const tile = new Konva.Rect({
            x: 0,
            y: 0,
            // x: x,
            // y: y,
            width: width,
            height: height,
            fill: '#eee8f5',
            // fill: '#8d8d8d',
            stroke: 'red',
            strokeWidth: 2,
            // stroke: 'black',
            // strokeWidth: 0.1,
            opacity: 1,
        });
        tileGroup.add(tile);
        layoutAreaGroup.add(tileGroup);

        if (content) {

            if (lastMachineKey === "MBO XL") {

                const poseWidth = sheetLayout.pose.width;
                const poseHeight = sheetLayout.pose.height;

                const pose  = new Konva.Rect({
                    id: "pose",
                    x: 0,
                    y: 0,
                    width: poseWidth,
                    height: poseHeight,
                    // fill: "yellow",
                    // fill: "#C81365FF",
                    stroke: "black",
                    strokeWidth: 2,
                    opacity: 1,
                });
            }

        } else {

            const poseWidth = sheetLayout.pose.width;
            const poseHeight = sheetLayout.pose.height;

            const hPoses = Math.floor(width / poseWidth);
            const hRemaining = width - (hPoses * poseWidth);
            const hSpace = hRemaining / (hPoses + 1);

            const vPoses = Math.floor(height / poseHeight);
            const vRemaining = height - (vPoses * poseHeight);
            const vSpace = vRemaining / (vPoses + 1);


            for (let m = hSpace; m <= width - poseWidth; m+=(poseWidth + hSpace)) {
                for (let n = vSpace; n <= height - poseHeight; n+=(poseHeight + vSpace)) {
                    const pose  = new Konva.Rect({
                        id: "pose",
                        x: m,
                        y: n,
                        // x: sheetLayout.cutSheet.x,
                        // y: sheetLayout.cutSheet.y,
                        width: poseWidth,
                        height: poseHeight,
                        // width: sheetLayout.cutSheet.width,
                        // height: sheetLayout.cutSheet.height,
                        // fill: "yellow",
                        // fill: "#C81365FF",
                        stroke: "black",
                        strokeWidth: 0.2,
                        opacity: 1,
                    });
                    tileGroup.add(pose);
                }
            }

        }

    }


}

const showFirstTileWithCutBufferDimensionLines = (sheetLayout, machineGroup, options) => {
    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.layoutArea.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.firstTileWithCutBuffer.width,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.layoutArea.y,
        color: options.color,
        length: sheetLayout.firstTileWithCutBuffer.height,
    }));

}

const showFirstTileDimensionLines = (sheetLayout, machineGroup, options) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.layoutArea.x + sheetLayout.firstTile.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.firstTile.width,
    }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.layoutArea.y + sheetLayout.firstTile.y,
        color: options.color,
        length: sheetLayout.firstTile.height,
    }));

}

const showLayoutAreaDimensionLines = (sheetLayout, machineGroup, options) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    // used area - START
    // pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
    //     x: 0,
    //     distance: options.distance,
    //     color: options.color,
    //     length: sheetLayout.layoutArea.x,
    // }));

    pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
        x: sheetLayout.layoutArea.x,
        distance: options.distance,
        color: options.color,
        length: sheetLayout.layoutArea.width,
    }));

    // pressSheetGroup.add(horizontalDimensionLine(machineGroup, {
    //     x: sheetLayout.layoutArea.x + sheetLayout.layoutArea.width,
    //     distance: options.distance,
    //     color: options.color,
    //     length: sheetLayout.pressSheet.width - (sheetLayout.layoutArea.x + sheetLayout.layoutArea.width),
    // }));

    // pressSheetGroup.add(verticalDimensionLine(machineGroup, {
    //     distance: options.distance,
    //     y: 0,
    //     color: options.color,
    //     length: sheetLayout.layoutArea.y,
    // }));

    pressSheetGroup.add(verticalDimensionLine(machineGroup, {
        distance: options.distance,
        y: sheetLayout.layoutArea.y,
        color: options.color,
        length: sheetLayout.layoutArea.height,
    }));

    // pressSheetGroup.add(verticalDimensionLine(machineGroup, {
    //     distance: options.distance,
    //     y: sheetLayout.layoutArea.y + sheetLayout.layoutArea.height,
    //     color: options.color,
    //     length: (sheetLayout.pressSheet.height) - (sheetLayout.layoutArea.y + sheetLayout.layoutArea.height),
    // }));

}

const showTrimLines = (sheetLayout, machineGroup) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

    pressSheetGroup.add(horizontalTrimLine(sheetLayout.trimLines.top));
    pressSheetGroup.add(horizontalTrimLine(sheetLayout.trimLines.bottom));
    pressSheetGroup.add(verticalTrimLine(sheetLayout.trimLines.left));
    pressSheetGroup.add(verticalTrimLine(sheetLayout.trimLines.right));
}

const showCutSheet = (sheetLayout, machineGroup) => {

    const pressSheetGroup = machineGroup.findOne("#pressSheetGroup");

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
        fill: "#888888",
        // fill: "#ff7700",
        opacity: 1

    });
    pressSheetGroup.add(cutSheetGripMarginRect);

}

// generic

const horizontalDimensionLine = (machineGroup, options) => {

    const pressSheet = machineGroup.findOne("#pressSheet");

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

const verticalDimensionLine = (machineGroup, options) => {

    const pressSheet = machineGroup.findOne("#pressSheet");

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

let calculatedData = null;
const calc = (input, machineIndex, content) => {

    if (machineIndex > 0) {
        input.cutSpacing = {
            horizontal: 0,
            vertical: 0,
        };
    }

    fetch(`/test/${input.scriptId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        // body: JSON.stringify(input)
    })
        .then(response => response.json())
        .then(data => {
            // console.log(data);
            calculatedData = data;
            displayAllTextualExplanation(data, data.metaData.jobNumber);
        })
        .catch(error => {
            console.error('Error loading JSON:', error);
        });
}


const displayAllTextualExplanation = (data, jobId) => {
    const textualExplanation = document.getElementById("textual-explanation");
    textualExplanation.innerHTML = "";

    let ctr = 0;

    Object.keys(data.parts).map((partId) => {

        const partDiv = document.createElement("div");

        const partTitleContent = `<div>${partId}</div>`;
        partDiv.innerHTML = partTitleContent;


        data.parts[partId].actionPaths.map((path) => {
            const uuid = crypto.randomUUID();
            const actionDiv = document.createElement("div");

            let divContent = "";

            divContent += `<div style="margin-bottom: 20px">`;
            divContent += `<a href="/display.html?jobId=${jobId}&partId=${partId}&impId=${path.id}" target="_blank">show impositions</a><div class="title"><input type="radio" name="${partId}" value="${path.id}"><div style="display: inline-block"><span class="show-details" onclick="toggleDetails('${uuid}')">&#9660;</span>${path.designation}</div></div>`;
            divContent += `<div class = "details" id="${uuid}" >`;
            path.nodes.map((node) => {

                divContent += `<div class="machine">`;
                divContent += `<div class="sub-title">${node.machine}</div>`;
                divContent += `<div><div class="label">Zone:</div> ${node.zone.width}x${node.zone.height}mm</div>`;
                divContent += `<div><div class="label">Number of input sheets:</div> ${node.todo.cutSheetCount}</div>`;
                divContent += `<div><div class="label">Imposition:</div> ${node.gridFitting.cols} x ${node.gridFitting.rows} ${node.gridFitting.rotated ? "R" : "U"}</div>`;
                divContent += `<div><div class="label">Setup duration:</div> ${node.setupDuration} min</div>`;
                divContent += `<div><div class="label">Run duration:</div> ${node.runDuration} min</div>`;
                divContent += `<div><div class="label">Cost:</div> ${node.cost}€</div>`;
                divContent += `</div>`;
                // console.log(node);
            })
            divContent += `</div>`;
            divContent += `</div>`;

            actionDiv.innerHTML = divContent;

            partDiv.appendChild(actionDiv);
        })

        textualExplanation.appendChild(partDiv);

    });



    // const textualExplanation = document.getElementById("textual-explanation");
    // textualExplanation.innerHTML = "";
    // data.actions.map(item => {
    //     if (item.actionType === "print" || item.actionType === "print" || item.actionType === "folding" || item.actionType === "stitching" || item.actionType === "ctp") {
    //         const actionDiv = document.createElement("div");
    //         actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
    //         actionDiv.innerHTML += `<div class="title">${item.machine}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">min:</div> ${item.minSheet.width} x ${item.minSheet.height}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">max:</div> ${item.maxSheet.width} x ${item.maxSheet.height}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Input sheet:</div> ${item.inputSheet.width} x ${item.inputSheet.height}</div>`;
    //
    //         if (item.actionType === "print") {
    //             actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Products per sheet:</div> ${item.productsPerSheet}</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Required sheet count:</div> ${item.printingSheets}</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Sheet price:</div> ${item.sheetPrice}€</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Paper cost per product:</div> ${item.paperCostPerProduct}€</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Printing paper cost:</div> ${item.printingPaperCost}€</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
    //         }
    //
    //         if (item.actionType === "folding") {
    //             actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
    //         }
    //
    //         if (item.actionType === "stitching") {
    //             actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
    //         }
    //
    //         if (item.actionType === "ctp") {
    //             actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
    //             actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
    //         }
    //
    //         actionDiv.innerHTML += `</div>`;
    //         textualExplanation.appendChild(actionDiv);
    //     }
    //
    //     if (item.actionType === "trim") {
    //         const actionDiv = document.createElement("div");
    //         actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
    //         actionDiv.innerHTML += `<div class="sub-title">Polar 115 (trim)</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Number of handfuls:</div> ${item.numberOfHandfuls}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Number of cuts:</div> ${item.numberOfCuts}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
    //         actionDiv.innerHTML += `</div>`;
    //         textualExplanation.appendChild(actionDiv);
    //     }
    //
    //     if (item.actionType === "cut") {
    //         const actionDiv = document.createElement("div");
    //         actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
    //         actionDiv.innerHTML += `<div class="sub-title">Polar 115 (cut & trim)</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Number of handfuls:</div> ${item.numberOfHandfuls}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Number of cuts:</div> ${item.numberOfCuts}</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
    //         actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
    //         actionDiv.innerHTML += `</div>`;
    //         textualExplanation.appendChild(actionDiv);
    //     }
    //
    //     if (item.actionType === "rotation") {
    //         const actionDiv = document.createElement("div");
    //         actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
    //         actionDiv.innerHTML += `<div style="padding-left: 5px"><i>Rotation</i></div>`;
    //         actionDiv.innerHTML += `</div>`;
    //         textualExplanation.appendChild(actionDiv);
    //     }
    //
    // });
    //
    // const totalDiv = document.createElement("div");
    // totalDiv.innerHTML = `<div style="margin-bottom: 20px">`;
    // totalDiv.innerHTML += `<div class="title">Total</div>`;
    // totalDiv.innerHTML += `<div><div class="label">Duration:</div> ${data.total.totalDuration} min</div>`;
    // totalDiv.innerHTML += `<div><div class="label">Cost:</div> ${data.total.totalCost}€</div>`;
    // totalDiv.innerHTML += `</div>`;
    // textualExplanation.appendChild(totalDiv);

}

const displayTextualExplanation = (data) => {
    const textualExplanation = document.getElementById("textual-explanation");
    textualExplanation.innerHTML = "";
    data.actions.map(item => {
        if (item.actionType === "print" || item.actionType === "print" || item.actionType === "folding" || item.actionType === "stitching" || item.actionType === "ctp") {
            const actionDiv = document.createElement("div");
            actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
            actionDiv.innerHTML += `<div class="title">${item.machine}</div>`;
            actionDiv.innerHTML += `<div><div class="label">min:</div> ${item.minSheet.width} x ${item.minSheet.height}</div>`;
            actionDiv.innerHTML += `<div><div class="label">max:</div> ${item.maxSheet.width} x ${item.maxSheet.height}</div>`;
            actionDiv.innerHTML += `<div><div class="label">Input sheet:</div> ${item.inputSheet.width} x ${item.inputSheet.height}</div>`;

            if (item.actionType === "print") {
                actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
                actionDiv.innerHTML += `<div><div class="label">Products per sheet:</div> ${item.productsPerSheet}</div>`;
                actionDiv.innerHTML += `<div><div class="label">Required sheet count:</div> ${item.printingSheets}</div>`;
                actionDiv.innerHTML += `<div><div class="label">Sheet price:</div> ${item.sheetPrice}€</div>`;
                actionDiv.innerHTML += `<div><div class="label">Paper cost per product:</div> ${item.paperCostPerProduct}€</div>`;
                actionDiv.innerHTML += `<div><div class="label">Printing paper cost:</div> ${item.printingPaperCost}€</div>`;
                actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
            }

            if (item.actionType === "folding") {
                actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
                actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
            }

            if (item.actionType === "stitching") {
                actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
                actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
            }

            if (item.actionType === "ctp") {
                actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
                actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
                actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
            }

            actionDiv.innerHTML += `</div>`;
            textualExplanation.appendChild(actionDiv);
        }

        if (item.actionType === "trim") {
            const actionDiv = document.createElement("div");
            actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
            actionDiv.innerHTML += `<div class="sub-title">Polar 115 (trim)</div>`;
            actionDiv.innerHTML += `<div><div class="label">Number of handfuls:</div> ${item.numberOfHandfuls}</div>`;
            actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
            actionDiv.innerHTML += `<div><div class="label">Number of cuts:</div> ${item.numberOfCuts}</div>`;
            actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
            actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
            actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
            actionDiv.innerHTML += `</div>`;
            textualExplanation.appendChild(actionDiv);
        }

        if (item.actionType === "cut") {
            const actionDiv = document.createElement("div");
            actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
            actionDiv.innerHTML += `<div class="sub-title">Polar 115 (cut & trim)</div>`;
            actionDiv.innerHTML += `<div><div class="label">Number of handfuls:</div> ${item.numberOfHandfuls}</div>`;
            actionDiv.innerHTML += `<div><div class="label">Number of sheets:</div> ${item.numberOfSheets}</div>`;
            actionDiv.innerHTML += `<div><div class="label">Number of cuts:</div> ${item.numberOfCuts}</div>`;
            actionDiv.innerHTML += `<div><div class="label">Setup duration:</div> ${item.setupDuration} min</div>`;
            actionDiv.innerHTML += `<div><div class="label">Run duration:</div> ${item.runDuration} min</div>`;
            actionDiv.innerHTML += `<div><div class="label">Cost:</div> ${item.cost}€</div>`;
            actionDiv.innerHTML += `</div>`;
            textualExplanation.appendChild(actionDiv);
        }

        if (item.actionType === "rotation") {
            const actionDiv = document.createElement("div");
            actionDiv.innerHTML = `<div style="margin-bottom: 20px">`;
            actionDiv.innerHTML += `<div style="padding-left: 5px"><i>Rotation</i></div>`;
            actionDiv.innerHTML += `</div>`;
            textualExplanation.appendChild(actionDiv);
        }

    });

    const totalDiv = document.createElement("div");
    totalDiv.innerHTML = `<div style="margin-bottom: 20px">`;
    totalDiv.innerHTML += `<div class="title">Total</div>`;
    totalDiv.innerHTML += `<div><div class="label">Duration:</div> ${data.total.totalDuration} min</div>`;
    totalDiv.innerHTML += `<div><div class="label">Cost:</div> ${data.total.totalCost}€</div>`;
    totalDiv.innerHTML += `</div>`;
    textualExplanation.appendChild(totalDiv);

}

const displayMachineVariations = (data, input, machineIndex, content) => {

    const machineId = input.machines[machineIndex].id;
    const machineGroupId = `machineGroup-${machineId.replace(/\s+/g, '')}`;

    let machineGroup = baseLayer.findOne(`#${machineGroupId}`);

    if (!machineGroup) {
        machineGroup = new Konva.Group({
            id: machineGroupId,
            x: 0,
            y: machineIndex * 1200,
        });
        baseLayer.add(machineGroup);
    }

    showControlPanel(input, data, machineIndex, machineGroup, content);

    // if (data.length > 0) {
    //     show(data[0], machineGroup);
    // }
}

const showControlPanel = (input, data, machineIndex, machineGroup, content) => {
    const controlPanelGroup = new Konva.Group({
        id: "controlPanelGroup",
        x: 0,
        y: 0,
        width: baseLayer.attrs.width,
        height: 100,
    });
    machineGroup.add(controlPanelGroup);

    showControlPanelNumbers(data, machineGroup);
    showControlPanelSelectors(input, data, machineIndex, machineGroup, content);

}

const showControlPanelNumbers = (data, machineGroup) => {

    let maxSizes = {
        unRotated: {
            cols: 0,
            rows: 0,
        },
        rotated: {
            cols: 0,
            rows: 0,
        },
    };
    data.map((item) => {
        if (!item.rotated) {
            if (item.cols > maxSizes.unRotated.cols) {
                maxSizes.unRotated.cols = item.cols;
            }
            if (item.rows > maxSizes.unRotated.rows) {
                maxSizes.unRotated.rows = item.rows;
            }
        }
        if (item.rotated) {
            if (item.cols > maxSizes.rotated.cols) {
                maxSizes.rotated.cols = item.cols;
            }
            if (item.rows > maxSizes.rotated.rows) {
                maxSizes.rotated.rows = item.rows;
            }
        }
    });

    for (let i = 1; i <= maxSizes.unRotated.cols; i++) {
        showHorizontalSelectorNumber(machineGroup, i, 0);
    }

    for (let i = 1; i <= maxSizes.rotated.cols; i++) {
        showHorizontalSelectorNumber(machineGroup, i, 200);
    }

    for (let i = 1; i <= maxSizes.unRotated.rows; i++) {
        showVerticalSelectorNumber(machineGroup, i, 0);
    }

    for (let i = 1; i <= maxSizes.rotated.rows; i++) {
        showVerticalSelectorNumber(machineGroup, i, 200);
    }
}

const showHorizontalSelectorNumber = (machineGroup, i, offset) => {

    const controlPanelGroup = machineGroup.findOne("#controlPanelGroup");
    const number = new Konva.Text({
        x: i * 30 + offset,
        y: 5,
        width: 20,
        text: i.toString(),
        fontSize: 16,
        fontFamily: 'Helvetica Neue',
        fill: 'black',
        align: "center",
        verticalAlign: "middle"
    });
    controlPanelGroup.add(number);
}

const showVerticalSelectorNumber = (machineGroup, i, offset) => {

    const controlPanelGroup = machineGroup.findOne("#controlPanelGroup");
    const number = new Konva.Text({
        x: 5 + offset,
        y: i * 30,
        height: 20,
        text: i.toString(),
        fontSize: 16,
        fontFamily: 'Helvetica Neue',
        fill: 'black',
        align: "center",
        verticalAlign: "middle"
    });
    controlPanelGroup.add(number);
}

const showControlPanelSelectors = (input, data, machineIndex, machineGroup, content) => {

    const controlPanelGroup = machineGroup.findOne("#controlPanelGroup");

    for (let i in data) {

        const selector = new Konva.Rect({
            x: (data[i].cols * 30) + (data[i].rotated ? 200 : 0),
            y: (data[i].rows * 30),
            width: 20,
            height: 20,
            // fill: "black",
            stroke: "black",
            strokeWidth: 1,
        });

        selector.on("click", function () {

            input.zone = data[i].cutSheet;
            // console.log(data[i]);

            input["action-path"][input.machines[machineIndex].id] = data[i];

            const newContent = show(data[i], machineGroup, content, input["action-path"]);

            stage.height(machineGroup.getClientRect().height * (machineIndex + 1) + 400);

            // todo: delete machineGroups with higher indexes
            for (let j = machineIndex + 1; j < input.machines.length; j++) {

                const machineId = input.machines[j].id;
                const machineGroupId = `machineGroup-${machineId.replace(/\s+/g, '')}`;

                const machineGroup = baseLayer.findOne(`#${machineGroupId}`);
                if (machineGroup) {
                    machineGroup.remove();
                }
            }

            document.getElementById("textual-explanation").innerHTML = "";

            // calculate next machine's data
            calc(input, machineIndex + 1, newContent);
        });
        controlPanelGroup.add(selector);

    }
}

const sendToOrdo = () => {

    let isAllSelected = true;
    let selectedUuids = Object.keys(calculatedData.parts).map((partId) => {
        const selectedValue = document.querySelector(`input[name="${partId}"]:checked`)?.value;
        console.log(partId);
        console.log(selectedValue);
        isAllSelected = isAllSelected && !!selectedValue;
        return {partId: partId, value: selectedValue}
    });

    if (isAllSelected) {
        const payload = {
            jobId: calculatedData.metaData.jobNumber,
            selectedUuids: selectedUuids
        }

        fetch('/ordo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                console.log(data);
            })
            .catch(error => {
                console.error('Error loading JSON:', error);
            });


    }

}

// 👇 Expose it globally
window.calc = calc;
window.sendToOrdo = sendToOrdo;